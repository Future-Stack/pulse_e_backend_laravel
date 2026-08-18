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

Schedule::job(new SyncNppesJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace:discover-places --queue')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace:refresh-place-cache')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace:purge-place-cache')->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new ScreenLeieJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new ExpireVettingJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new RebuildSlateJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace:enforce-slot-integrity')->everyFiveMinutes()->withoutOverlapping();