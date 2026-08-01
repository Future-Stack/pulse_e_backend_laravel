<?php

namespace App\Console\Commands;

use App\Jobs\SyncNppesJob;
use Illuminate\Console\Command;

/**
 * Console command to run SyncNppesJob directly from CLI without Tinker REPL.
 * Usage: php artisan marketplace:sync-nppes
 */
class SyncNppesCommand extends Command
{
    protected $signature = 'marketplace:sync-nppes';
    protected $description = 'Download latest weekly NPPES file and sync matching providers';

    public function handle(): int
    {
        $this->info('Starting NPPES sync job...');
        
        SyncNppesJob::dispatchSync();

        $this->info('NPPES sync job execution completed.');

        return self::SUCCESS;
    }
}
