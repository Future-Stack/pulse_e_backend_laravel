<?php

namespace Database\Seeders;

use App\Models\LifeJourney;
use App\Models\LifeStage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LifeStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            'Teen',
            'Reproductive Years',
            'Pregnancy',
            'Postpartum',
            'Perimenopause',
            'Menopause',
        ];

        foreach ($stages as $stage) {
            LifeStage::updateOrCreate(
                ['title' => $stage],
            );
        }

    }
}
