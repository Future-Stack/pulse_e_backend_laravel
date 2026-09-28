<?php

namespace Database\Seeders;

use App\Models\MenopauseSymptomInsight;
use Illuminate\Database\Seeder;

class PerimenopauseDataSeeder extends Seeder
{
    public function run(): void
    {
        $insights = [
            [
                'title' => 'Hot Flashes → Sleep',
                'link_percentage' => 87,
                'description' => 'Hot flash episodes after 10pm directly correlate with 47% reduction in deep sleep duration.',
                'category' => 'symptom_matrix',
                'display_order' => 1,
            ],
            [
                'title' => 'Sleep → Mood',
                'link_percentage' => 72,
                'description' => 'Under 6hrs sleep raises irritability and anxiety scores by 34% the following day.',
                'category' => 'symptom_matrix',
                'display_order' => 2,
            ],
            [
                'title' => 'Hot Flashes → Mood',
                'link_percentage' => 79,
                'description' => 'Days with 5+ episodes show elevated mood disruption in 83% of logged entries.',
                'category' => 'symptom_matrix',
                'display_order' => 3,
            ],
        ];

        foreach ($insights as $insight) {
            MenopauseSymptomInsight::updateOrCreate(
                ['title' => $insight['title']],
                $insight
            );
        }
    }
}
