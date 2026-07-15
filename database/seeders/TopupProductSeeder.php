<?php

namespace Database\Seeders;

use App\Models\TopupProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TopupProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'slug' => 'coaching_sessions_20',
                'name' => '+20 Coaching Sessions',
                'description' => 'One-time top-up that expires at the end of the month.',
                'topup_kind' => 'coaching_sessions',
                'limit' => 20,
                'price' => 2.99,
                'status' => 1,
            ],
            [
                'slug' => 'skin_scans_5',
                'name' => '+5 Skin Scans',
                'description' => 'One-time top-up that expires at the end of the month.',
                'topup_kind' => 'skin_scans',
                'limit' => 5,
                'price' => 1.99,
                'status' => 1,
            ],
        ];

        foreach ($products as $product) {
            TopupProduct::updateOrCreate(['slug' => $product['slug']], $product);
        }
    }
}
