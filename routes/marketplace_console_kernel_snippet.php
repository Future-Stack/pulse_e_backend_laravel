<?php

/*
|--------------------------------------------------------------------------
| Laravel 10 (and earlier) reference — app/Console/Kernel.php
|--------------------------------------------------------------------------
| If your app predates Laravel 11's routes/console.php scheduler, paste the
| body below into the schedule(Schedule $schedule) method of your Kernel
| instead of using marketplace_console.php.
*/

// protected function schedule(Schedule $schedule): void
// {
//     $schedule->job(new \App\Jobs\SyncNppesJob)->weekly()->mondays()->at('03:00');
//
//     $schedule->call(function () {
//         foreach (\App\Models\Metro::where('active', true)->get() as $metro) {
//             foreach (\App\Models\ProviderCategory::where('active', true)->get() as $category) {
//                 \App\Jobs\DiscoverPlacesJob::dispatch($metro, $category);
//             }
//         }
//     })->weekly()->tuesdays()->at('03:00');
//
//     $schedule->command('marketplace:refresh-place-cache')->dailyAt('04:00');
//     $schedule->command('marketplace:purge-place-cache')->dailyAt('05:00');
//     $schedule->job(new \App\Jobs\ScreenLeieJob)->monthly();
//     $schedule->job(new \App\Jobs\ExpireVettingJob)->dailyAt('01:00');
//     $schedule->job(new \App\Jobs\RebuildSlateJob)->dailyAt('02:00');
//     $schedule->command('marketplace:enforce-slot-integrity')->dailyAt('02:30');
// }
