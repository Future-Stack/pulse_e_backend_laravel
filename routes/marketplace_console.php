<?php

use App\Jobs\ExpireVettingJob;
use App\Jobs\RebuildSlateJob;
use App\Jobs\ScreenLeieJob;
use App\Jobs\SyncNppesJob;
use App\Models\Metro;
use App\Models\ProviderCategory;
use App\Jobs\DiscoverPlacesJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Provider Marketplace scheduler
|--------------------------------------------------------------------------
| Laravel 11+: merge this into routes/console.php (or require it from there).
| Laravel 10 and earlier: move these into the schedule() method of
| app/Console/Kernel.php using the same $schedule-> calls (see that file's
| commented example below this one, marketplace_console_kernel_snippet.php).
|
| All of these dispatch onto the queue — make sure a queue worker
| (php artisan queue:work, ideally via Supervisor/Horizon) is running,
| or nothing here actually executes.
*/

Schedule::job(new SyncNppesJob)->weekly()->mondays()->at('03:00');

Schedule::command('marketplace:discover-places')->weekly()->mondays()->at('11:00');

Schedule::command('marketplace:refresh-place-cache')->dailyAt('04:00');
Schedule::command('marketplace:purge-place-cache')->dailyAt('05:00');
Schedule::job(new ScreenLeieJob)->monthly();
Schedule::job(new ExpireVettingJob)->dailyAt('01:00');
Schedule::job(new RebuildSlateJob)->dailyAt('02:00');
Schedule::command('marketplace:enforce-slot-integrity')->dailyAt('02:30');

