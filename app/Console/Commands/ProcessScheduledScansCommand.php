<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledScans;
use Illuminate\Console\Command;

final class ProcessScheduledScansCommand extends Command
{
    protected $signature = 'scans:process';

    protected $description = 'Process all scheduled scans that are due to run';

    public function handle(): int
    {
        $this->info('Processing scheduled scans...');

        ProcessScheduledScans::dispatch();

        $this->comment('Scheduled scans job has been dispatched.');
        
        return self::SUCCESS;
    }
}