<?php

namespace Database\Seeders;

use App\Models\MarketplaceLifeStage;
use App\Models\ProviderCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the Part 1 Life-Stage -> Provider Category mapping from the spec, including
 * NUCC taxonomy codes and Google Places query recipes. Run after migrations:
 *   php artisan db:seed --class=ProviderTaxonomySeeder
 *
 * IMPORTANT: per the spec's verification note, all NUCC codes must be validated
 * against the current NUCC taxonomy release before this seeder is trusted in prod.
 */
class ProviderTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $lifeStages = [
            'beauty-radiance' => 'Beauty & Radiance',
            'cycle-fertility' => 'Cycle & Fertility',
            'athlete' => 'Athlete',
            'perimenopause-menopause-vitality' => 'Perimenopause / Menopause & Vitality',
            'pregnancy-postpartum' => 'Pregnancy & Postpartum',
            'lifelong-thriving' => 'Lifelong Thriving',
        ];

        $stageModels = [];
        foreach ($lifeStages as $slug => $name) {
            $stageModels[$slug] = MarketplaceLifeStage::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // Each category: slug, display_name, vetting_tier, requires_npi,
        // nucc codes [[code, label, prefix], ...], places [[type, keyword], ...]
        $categories = $this->categoryDefinitions();

        $categoryModels = [];
        foreach ($categories as $def) {
            $category = ProviderCategory::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'display_name' => $def['display_name'],
                    'vetting_tier' => $def['vetting_tier'],
                    'requires_npi' => $def['requires_npi'],
                    'active' => true,
                ]
            );

            $category->taxonomyCodes()->delete();
            foreach ($def['nucc'] as [$code, $label, $prefix]) {
                $category->taxonomyCodes()->create([
                    'nucc_code' => $code,
                    'label' => $label,
                    'nucc_prefix' => $prefix,
                ]);
            }

            $category->placeQueries()->delete();
            foreach ($def['places'] as [$type, $keyword]) {
                $category->placeQueries()->create([
                    'places_type' => $type,
                    'keyword' => $keyword,
                ]);
            }

            $categoryModels[$def['slug']] = $category;
        }

        // Stage -> category eligibility + display order
        $stageCategoryMap = [
            'beauty-radiance' => ['dermatology', 'plastic-reconstructive-surgery', 'medical-spa', 'esthetician-skincare-studio', 'registered-dietitian'],
            'cycle-fertility' => ['obgyn', 'reproductive-endocrinology-infertility', 'fertility-clinic', 'acupuncturist-fertility', 'genetic-counselor'],
            'athlete' => ['sports-medicine-physician', 'physical-therapist-sports', 'orthopedics', 'chiropractor-sports', 'sports-dietitian', 'performance-recovery-studio'],
            'perimenopause-menopause-vitality' => ['menopause-specialist', 'obgyn', 'endocrinology', 'hrt-hormone-clinic', 'pelvic-floor-pt-meno', 'therapist-mental-health-meno'],
            'pregnancy-postpartum' => ['obgyn', 'maternal-fetal-medicine', 'certified-nurse-midwife', 'doula', 'lactation-consultant', 'pelvic-floor-pt-postpartum', 'perinatal-mental-health'],
            'lifelong-thriving' => ['primary-care', 'cardiology', 'endocrinology', 'longevity-functional-medicine', 'registered-dietitian', 'therapist-mental-health', 'geriatric-medicine'],
        ];

        foreach ($stageCategoryMap as $stageSlug => $categorySlugs) {
            foreach ($categorySlugs as $priority => $categorySlug) {
                $stageModels[$stageSlug]->categories()->syncWithoutDetaching([
                    $categoryModels[$categorySlug]->id => ['display_priority' => $priority + 1],
                ]);
            }
        }
    }

    private function categoryDefinitions(): array
    {
        return [
            // 1. Beauty & Radiance
            ['slug' => 'dermatology', 'display_name' => 'Dermatology', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207N00000X', 'Dermatology', false]], 'places' => [['doctor', 'dermatologist']]],
            ['slug' => 'plastic-reconstructive-surgery', 'display_name' => 'Plastic & Reconstructive Surgery', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['208200000X', 'Plastic & Reconstructive Surgery', false]], 'places' => [[null, 'plastic surgeon']]],
            ['slug' => 'medical-spa', 'display_name' => 'Medical Spa', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [['261Q', 'Clinic/Center (org, if physician-directed)', true]], 'places' => [['spa', 'medical spa']]],
            ['slug' => 'esthetician-skincare-studio', 'display_name' => 'Esthetician / Skincare Studio', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [], 'places' => [['beauty_salon', 'esthetician']]],
            ['slug' => 'registered-dietitian', 'display_name' => 'Registered Dietitian', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['133V00000X', 'Dietitian, Registered', false]], 'places' => [[null, 'registered dietitian']]],

            // 2. Cycle & Fertility
            ['slug' => 'obgyn', 'display_name' => 'OB-GYN', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207V00000X', 'Obstetrics & Gynecology', false]], 'places' => [[null, 'obgyn']]],
            ['slug' => 'reproductive-endocrinology-infertility', 'display_name' => 'Reproductive Endocrinology & Infertility (REI)', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207VE0102X', 'Reproductive Endocrinology', false]], 'places' => [[null, 'fertility specialist']]],
            ['slug' => 'fertility-clinic', 'display_name' => 'Fertility Clinic (org-level)', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['261Q', 'Clinic/Center (org NPI)', true]], 'places' => [[null, 'fertility clinic']]],
            ['slug' => 'acupuncturist-fertility', 'display_name' => 'Acupuncturist (fertility support)', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [['171100000X', 'Acupuncturist', false]], 'places' => [[null, 'fertility acupuncture']]],
            ['slug' => 'genetic-counselor', 'display_name' => 'Genetic Counselor', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [['170300000X', 'Genetic Counselor, MS', false]], 'places' => [[null, 'genetic counselor']]],

            // 3. Athlete
            ['slug' => 'sports-medicine-physician', 'display_name' => 'Sports Medicine Physician', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [
                    ['207QS0010X', 'Family Medicine, Sports Medicine', false],
                    ['2081S0010X', 'PM&R, Sports Medicine', false],
                    ['207XX0005X', 'Orthopaedic Surgery, Sports Medicine', false],
                ], 'places' => [['doctor', 'sports medicine doctor']]],
            ['slug' => 'physical-therapist-sports', 'display_name' => 'Physical Therapist (sports)', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => true,
                'nucc' => [['225100000X', 'Physical Therapist', false], ['2251S0007X', 'PT, Sports', false]], 'places' => [[null, 'sports physical therapy']]],
            ['slug' => 'orthopedics', 'display_name' => 'Orthopedics', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207X00000X', 'Orthopaedic Surgery', false]], 'places' => [[null, 'orthopedic doctor']]],
            ['slug' => 'chiropractor-sports', 'display_name' => 'Chiropractor', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => true,
                'nucc' => [['111N00000X', 'Chiropractor', false], ['111NS0005X', 'Chiropractor, Sports', false]], 'places' => [[null, 'sports chiropractor']]],
            ['slug' => 'sports-dietitian', 'display_name' => 'Sports Dietitian', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['133V00000X', 'Dietitian, Registered (CSSD)', false]], 'places' => [[null, 'sports nutritionist']]],
            ['slug' => 'performance-recovery-studio', 'display_name' => 'Performance / Recovery Studio', 'vetting_tier' => 'consumer', 'requires_npi' => false,
                'nucc' => [], 'places' => [['gym', 'sports performance training'], ['gym', 'athletic recovery']]],

            // 4. Perimenopause / Menopause & Vitality
            ['slug' => 'menopause-specialist', 'display_name' => 'Menopause Specialist (MSCP)', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [], 'places' => [[null, 'menopause specialist']]],
            ['slug' => 'endocrinology', 'display_name' => 'Endocrinology', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207RE0101X', 'Endocrinology, Diabetes & Metabolism', false]], 'places' => [[null, 'endocrinologist']]],
            ['slug' => 'hrt-hormone-clinic', 'display_name' => 'HRT / Hormone Clinic', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [], 'places' => [[null, 'hormone replacement therapy clinic']]],
            ['slug' => 'pelvic-floor-pt-meno', 'display_name' => 'Pelvic-Floor Physical Therapist', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => true,
                'nucc' => [['225100000X', 'Physical Therapist, Pelvic Health', false]], 'places' => [[null, 'pelvic floor physical therapy']]],
            ['slug' => 'therapist-mental-health-meno', 'display_name' => 'Therapist / Mental Health', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['103T00000X', 'Psychologist', false], ['101YM0800X', 'Counselor, Mental Health', false]],
                'places' => [[null, 'therapist menopause'], [null, "women's therapist"]]],

            // 5. Pregnancy & Postpartum
            ['slug' => 'maternal-fetal-medicine', 'display_name' => 'Maternal-Fetal Medicine', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207VM0101X', 'Maternal & Fetal Medicine', false]], 'places' => [[null, 'maternal fetal medicine']]],
            ['slug' => 'certified-nurse-midwife', 'display_name' => 'Certified Nurse Midwife', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['367A00000X', 'Advanced Practice Midwife', false]], 'places' => [[null, 'certified nurse midwife']]],
            ['slug' => 'doula', 'display_name' => 'Doula', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [['374J00000X', 'Doula', false]], 'places' => [[null, 'doula']]],
            ['slug' => 'lactation-consultant', 'display_name' => 'Lactation Consultant (IBCLC)', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [['174N00000X', 'Lactation Consultant, IBCLC', false]], 'places' => [[null, 'lactation consultant']]],
            ['slug' => 'pelvic-floor-pt-postpartum', 'display_name' => 'Pelvic-Floor Physical Therapist (postpartum)', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => true,
                'nucc' => [['225100000X', 'Physical Therapist, Pelvic Health', false]], 'places' => [[null, 'pelvic floor physical therapy postpartum']]],
            ['slug' => 'perinatal-mental-health', 'display_name' => 'Perinatal Mental Health', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['2084P0800X', 'Psychiatry & Neurology, Perinatal Mental Health', false], ['103T00000X', 'Psychologist (PMH-C)', false]],
                'places' => [[null, 'postpartum therapist']]],

            // 6. Lifelong Thriving
            ['slug' => 'primary-care', 'display_name' => 'Primary Care', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207Q00000X', 'Family Medicine', false], ['207R00000X', 'Internal Medicine', false]], 'places' => [[null, 'primary care physician']]],
            ['slug' => 'cardiology', 'display_name' => 'Cardiology', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207RC0000X', 'Cardiovascular Disease', false]], 'places' => [[null, 'cardiologist']]],
            ['slug' => 'longevity-functional-medicine', 'display_name' => 'Longevity / Functional Medicine', 'vetting_tier' => 'licensed_nonmedical', 'requires_npi' => false,
                'nucc' => [], 'places' => [[null, 'longevity clinic'], [null, 'functional medicine']]],
            ['slug' => 'therapist-mental-health', 'display_name' => 'Therapist / Mental Health', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['103T00000X', 'Psychologist', false], ['101YM0800X', 'Counselor, Mental Health', false]], 'places' => [[null, 'therapist']]],
            ['slug' => 'geriatric-medicine', 'display_name' => 'Geriatric Medicine', 'vetting_tier' => 'medical', 'requires_npi' => true,
                'nucc' => [['207QG0300X', 'Family Medicine, Geriatric', false], ['207RG0300X', 'Internal Medicine, Geriatric', false]], 'places' => [[null, 'geriatric doctor']]],
        ];
    }
}
