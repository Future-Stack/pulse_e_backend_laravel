<?php

namespace Database\Seeders;

use App\Models\PregnancyWeeklyGuide;
use Illuminate\Database\Seeder;

class PregnancyWeeklyGuideSeeder extends Seeder
{
    public function run(): void
    {
        $guides = [
            [
                'week_number' => 24,
                'trimester' => 'Second Trimester',
                'baby_size_comparison' => 'an ear of corn',
                'approx_size_text' => 'about 30cm, 600g',
                'baby_development' => 'Lungs developing rapidly. Eyes partially open. Responds to sound.',
                'your_body' => 'Uterus now above belly button. Braxton Hicks contractions may begin.',
                'nutrition_focus' => 'Iron & Omega-3 critical. Aim for 300 extra calories/day.',
                'safe_exercise' => 'Swimming, walking, prenatal yoga all safe and beneficial.',
                'clinical_warning_signs' => 'Seek immediate care for: severe headache, vision changes, sudden swelling, decreased fetal movement, or vaginal bleeding.',
            ],
            [
                'week_number' => 12,
                'trimester' => 'First Trimester',
                'baby_size_comparison' => 'a plum',
                'approx_size_text' => 'about 5.4cm, 14g',
                'baby_development' => 'Reflexes are developing. Baby can curl toes and make sucking motions.',
                'your_body' => 'Morning sickness may begin to fade as hormone levels stabilize.',
                'nutrition_focus' => 'Folate and magnesium to maintain energy and cell growth.',
                'safe_exercise' => 'Gentle walking and pelvic floor strength work.',
                'clinical_warning_signs' => 'Seek care for: severe cramping, fever, or bleeding.',
            ],
            [
                'week_number' => 28,
                'trimester' => 'Third Trimester',
                'baby_size_comparison' => 'an eggplant',
                'approx_size_text' => 'about 37cm, 1000g',
                'baby_development' => 'Eyelashes have formed. Baby is practicing breathing movements.',
                'your_body' => 'Backaches and shortness of breath can emerge as baby grows upward.',
                'nutrition_focus' => 'Calcium and vitamin D for baby bone mineralization.',
                'safe_exercise' => 'Low-impact swimming and prenatal stretches.',
                'clinical_warning_signs' => 'Seek care for: sudden swelling in hands or face, blurred vision, or severe pain.',
            ],
            [
                'week_number' => 36,
                'trimester' => 'Third Trimester',
                'baby_size_comparison' => 'a papaya',
                'approx_size_text' => 'about 47cm, 2600g',
                'baby_development' => 'Baby is shedding vernix and lanugo, getting ready for birth.',
                'your_body' => 'Pelvic pressure increases as baby drops lower into the pelvis.',
                'nutrition_focus' => 'Frequent small nutrient-dense meals to ease heartburn.',
                'safe_exercise' => 'Pelvic tilts, walking, birth ball exercises.',
                'clinical_warning_signs' => 'Seek care for: fluid leaking, regular painful contractions, decreased movement.',
            ],
        ];

        // Seed 1 through 40 with sensible defaults if not explicitly set
        for ($w = 1; $w <= 40; $w++) {
            $existing = collect($guides)->firstWhere('week_number', $w);
            if ($existing) {
                PregnancyWeeklyGuide::updateOrCreate(['week_number' => $w], $existing);
            } else {
                $trimester = $w <= 13 ? 'First Trimester' : ($w <= 27 ? 'Second Trimester' : 'Third Trimester');
                PregnancyWeeklyGuide::updateOrCreate(
                    ['week_number' => $w],
                    [
                        'trimester' => $trimester,
                        'baby_size_comparison' => "healthy growth (Week {$w})",
                        'approx_size_text' => "approx " . round($w * 1.25) . "cm",
                        'baby_development' => "Fetal organs and nervous system are progressing steadily during Week {$w}.",
                        'your_body' => "Body adapts to hormonal shifts and expanding maternal blood volume.",
                        'nutrition_focus' => 'Stay hydrated, prioritize lean proteins, leafy greens, and balanced meals.',
                        'safe_exercise' => 'Moderate walking and prenatal yoga approved by your healthcare provider.',
                        'clinical_warning_signs' => 'Seek immediate care for: severe headache, vision changes, sudden swelling, decreased fetal movement, or vaginal bleeding.',
                    ]
                );
            }
        }
    }
}
