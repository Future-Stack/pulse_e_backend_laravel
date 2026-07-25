<?php

namespace App\Jobs;

use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * leie:screen — monthly (spec 2.3).
 *
 * Downloads the OIG's "Updated LEIE Database" CSV — a stable URL that always
 * points to the current full exclusions list (replaced monthly) — and matches
 * on NPI. Hits set providers.status = excluded and write a vetting_records row.
 * Excluded is terminal without manual override (enforced in
 * Admin\ProviderController::transitionStatus).
 *
 * Name+DOB fallback matching is intentionally NOT implemented: the LEIE record
 * layout includes DOB, and matching on name+DOB against non-excluded provider
 * records would require storing provider DOB, which this schema does not
 * carry (providers are business-facing directory records, not PHI). NPI match
 * covers the large majority of enumerated providers; anything without an NPI
 * hit should be checked manually against https://exclusions.oig.hhs.gov before
 * being trusted, per the spec's own "Privacy Act prohibits SSN distribution,
 * use the Online Search to verify" guidance.
 */
class ScreenLeieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LEIE_URL = 'https://oig.hhs.gov/exclusions/downloadables/UPDATED.csv';

    public $timeout = 600;

    public function handle(): void
    {
        $excludedNpis = $this->fetchLeieExcludedNpis();

        if (empty($excludedNpis)) {
            Log::warning('ScreenLeieJob: no NPIs parsed from the LEIE file; skipping to avoid a false-clear run.');
            return;
        }

        $hits = Provider::whereIn('npi', $excludedNpis)
            ->where('status', '!=', 'excluded')
            ->get();

        foreach ($hits as $provider) {
            $provider->update(['status' => 'excluded']);

            $provider->vettingRecords()->create([
                'check_type' => 'leie',
                'status' => 'fail',
                'evidence_url' => 'https://exclusions.oig.hhs.gov/',
                'notes' => 'NPI matched against the OIG LEIE Updated Database.',
                'checked_at' => now(),
                'checked_by' => 'leie:screen job',
            ]);
        }

        Log::info("ScreenLeieJob: screened " . count($excludedNpis) . " excluded NPIs, flagged {$hits->count()} providers.");
    }

    /**
     * @return array<string> NPIs (10-digit strings) currently on the LEIE.
     */
    private function fetchLeieExcludedNpis(): array
    {
        $response = Http::timeout(120)->get(self::LEIE_URL);

        if (! $response->successful()) {
            Log::error('ScreenLeieJob: failed to download LEIE file', ['status' => $response->status()]);
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $response->body());
        $header = str_getcsv(array_shift($lines));
        $npiIndex = array_search('NPI', $header, true);

        if ($npiIndex === false) {
            Log::error('ScreenLeieJob: NPI column not found in LEIE header', ['header' => $header]);
            return [];
        }

        $npis = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $npi = trim($row[$npiIndex] ?? '');

            // LEIE uses "0000000000" for excluded parties without an enumerated NPI — skip those.
            if ($npi !== '' && $npi !== '0000000000' && strlen($npi) === 10) {
                $npis[] = $npi;
            }
        }

        return array_unique($npis);
    }
}
