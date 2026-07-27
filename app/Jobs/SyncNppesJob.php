<?php

namespace App\Jobs;

use App\Models\CategoryTaxonomyCode;
use App\Models\Provider;
use App\Services\ZipGeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * nppes:sync — weekly (spec 2.3).
 *
 * Downloads the current "Weekly Incremental NPI File" listed on
 * https://download.cms.gov/nppes/NPI_Files.html (CMS publishes a new weekly ZIP
 * every Monday covering the prior week), unzips it, stream-parses the
 * npidata_pfile_*.csv inside, and upserts any practitioner/org whose taxonomy
 * code(s) match category_taxonomy_codes and whose address falls in an active
 * metro. New rows enter as status = candidate — never active; that only happens
 * via the admin vetting workflow (ProviderController::transitionStatus).
 *
 * The main NPPES file has up to 15 taxonomy code slots per row
 * (Healthcare Provider Taxonomy Code_1..15) — we check all of them, not just
 * the primary one, since a provider can legitimately serve multiple categories.
 */
class SyncNppesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const FILE_INDEX_URL = 'https://download.cms.gov/nppes/NPI_Files.html';
    private const BASE_URL = 'https://download.cms.gov/nppes/';

    public $timeout = 3600; // the zip + CSV parse can run long; run this on a dedicated queue

    public function handle(ZipGeocodingService $zipGeocodingService): void
    {
        $taxonomyToCategory = CategoryTaxonomyCode::all()->groupBy('nucc_code');

        $zipPath = $this->downloadLatestWeeklyFile();

        if (! $zipPath) {
            Log::warning('SyncNppesJob: no weekly file found or download failed; skipping this run.');
            return;
        }

        $csvPath = $this->extractMainDataFile($zipPath);

        if (! $csvPath) {
            Log::error('SyncNppesJob: could not locate npidata_pfile CSV inside the downloaded ZIP.');
            return;
        }

        $count = 0;
        $skippedNoMetro = 0;

        foreach ($this->streamRows($csvPath) as $row) {
            $matchedCategoryIds = $this->matchedCategoryIds($row['taxonomy_codes'], $taxonomyToCategory);

            if ($matchedCategoryIds->isEmpty()) {
                continue; // not a taxonomy we track — skip without ever writing PHI-adjacent data
            }

            // Spec 2.3: only upsert practitioners "whose practice address falls in
            // an active metro." resolveMetro() geocodes+caches by ZIP for 30 days,
            // so repeat ZIPs (very common — many providers share a ZIP) are cheap
            // after the first hit; only taxonomy-matched rows reach this call.
            $metro = $row['zip'] ? $zipGeocodingService->resolveMetro($row['zip']) : null;

            if (! $metro) {
                $skippedNoMetro++;
                continue;
            }

            $isNew = ! Provider::where('npi', $row['npi'])->exists();

            $provider = Provider::updateOrCreate(
                ['npi' => $row['npi']],
                [
                    'display_name' => $row['display_name'],
                    'org_name' => $row['org_name'],
                    'phone_e164' => $row['phone_e164'],
                    'addr_line1' => $row['addr_line1'],
                    'addr_line2' => $row['addr_line2'],
                    'city' => $row['city'],
                    'state' => $row['state'],
                    'zip' => $row['zip'],
                    'metro_id' => $metro->id,
                    'source_nppes' => true,
                    'status' => $isNew ? 'candidate' : Provider::where('npi', $row['npi'])->value('status'),
                ]
            );

            foreach ($matchedCategoryIds as $categoryId) {
                $provider->categories()->syncWithoutDetaching([
                    $categoryId => ['source' => 'nppes_taxonomy'],
                ]);
            }

            $count++;
        }

        Storage::disk('local')->delete([$zipPath, $csvPath]);

        Log::info("SyncNppesJob: upserted {$count} providers from the weekly NPPES file ({$skippedNoMetro} taxonomy-matched rows skipped for falling outside every active metro).");
    }

    /**
     * Scrapes the NPI_Files.html index for the most recent
     * "...Weekly_V2.zip" link and downloads it to local storage.
     */
    private function downloadLatestWeeklyFile(): ?string
    {
        $html = Http::timeout(30)->get(self::FILE_INDEX_URL)->body();

        preg_match_all('/NPPES_Data_Dissemination_[\w]+_Weekly_V2\.zip/', $html, $matches);

        $filename = collect($matches[0] ?? [])->unique()->sort()->last(); // filenames sort chronologically

        if (! $filename) {
            return null;
        }

        $response = Http::timeout(600)->sink(storage_path("app/nppes/{$filename}"))
            ->get(self::BASE_URL . $filename);

        if (! $response->successful()) {
            return null;
        }

        return "nppes/{$filename}";
    }

    /**
     * The ZIP contains the main data file plus Other Name / Practice Location /
     * Endpoint reference files — we only want npidata_pfile_*.csv.
     */
    private function extractMainDataFile(string $zipRelativePath): ?string
    {
        $zip = new ZipArchive();
        $fullZipPath = storage_path("app/{$zipRelativePath}");
        $extractDir = storage_path('app/nppes/extracted');

        if ($zip->open($fullZipPath) !== true) {
            return null;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (str_starts_with($entry, 'npidata_pfile_') && str_ends_with($entry, '.csv')) {
                $zip->extractTo($extractDir, $entry);
                $zip->close();

                return "nppes/extracted/{$entry}";
            }
        }

        $zip->close();

        return null;
    }

    /**
     * Streams the CSV row by row (no full-file load into memory — these files
     * run several million rows) and yields only the fields we care about,
     * skipping deactivated NPIs.
     *
     * @return iterable<array>
     */
    private function streamRows(string $csvRelativePath): iterable
    {
        $handle = fopen(storage_path("app/{$csvRelativePath}"), 'r');

        if ($handle === false) {
            return;
        }

        $header = fgetcsv($handle);
        $columnIndex = array_flip($header);

        $taxonomyCols = [];
        for ($i = 1; $i <= 15; $i++) {
            $taxonomyCols[] = $columnIndex["Healthcare Provider Taxonomy Code_{$i}"] ?? null;
        }

        while (($data = fgetcsv($handle)) !== false) {
            // NPI Deactivation Date present -> deactivated, skip.
            $deactivationCol = $columnIndex['NPI Deactivation Date'] ?? null;
            if ($deactivationCol !== null && ! empty($data[$deactivationCol])) {
                continue;
            }

            $taxonomyCodes = collect($taxonomyCols)
                ->filter(fn ($col) => $col !== null && ! empty($data[$col]))
                ->map(fn ($col) => $data[$col])
                ->values()
                ->all();

            if (empty($taxonomyCodes)) {
                continue;
            }

            $entityType = $data[$columnIndex['Entity Type Code']] ?? '1';
            $isOrg = $entityType === '2';

            yield [
                'npi' => $data[$columnIndex['NPI']],
                'display_name' => $isOrg
                    ? ($data[$columnIndex['Provider Organization Name (Legal Business Name)']] ?? '')
                    : trim(($data[$columnIndex['Provider First Name']] ?? '') . ' ' . ($data[$columnIndex['Provider Last Name (Legal Name)']] ?? '')),
                'org_name' => $isOrg ? ($data[$columnIndex['Provider Organization Name (Legal Business Name)']] ?? null) : null,
                'phone_e164' => $this->normalizePhone($data[$columnIndex['Provider Business Practice Location Address Telephone Number']] ?? null),
                'addr_line1' => $data[$columnIndex['Provider First Line Business Practice Location Address']] ?? null,
                'addr_line2' => $data[$columnIndex['Provider Second Line Business Practice Location Address']] ?? null,
                'city' => $data[$columnIndex['Provider Business Practice Location Address City Name']] ?? null,
                'state' => $data[$columnIndex['Provider Business Practice Location Address State Name']] ?? null,
                'zip' => substr($data[$columnIndex['Provider Business Practice Location Address Postal Code']] ?? '', 0, 5),
                'taxonomy_codes' => $taxonomyCodes,
            ];
        }

        fclose($handle);
    }

    private function normalizePhone(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        return strlen($digits) === 10 ? '+1' . $digits : null;
    }

    /**
     * @param array<string> $rowCodes
     * @param \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> $taxonomyToCategory
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function matchedCategoryIds(array $rowCodes, $taxonomyToCategory)
    {
        $matched = collect();

        foreach ($rowCodes as $rowCode) {
            foreach ($taxonomyToCategory as $trackedCode => $codeRecords) {
                $isPrefix = $codeRecords->first()?->nucc_prefix ?? false;

                $matches = $isPrefix
                    ? str_starts_with($rowCode, rtrim($trackedCode, 'X*'))
                    : $rowCode === $trackedCode;

                if ($matches) {
                    $matched = $matched->merge($codeRecords->pluck('category_id'));
                }
            }
        }

        return $matched->unique()->values();
    }
}
