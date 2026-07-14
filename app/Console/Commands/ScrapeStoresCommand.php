<?php

namespace App\Console\Commands;

use App\Jobs\CheckStoreJob;
use Illuminate\Console\Command;

class ScrapeStoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:scrape {start=1} {end=1000000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Salla stores by ID range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $start = (int) $this->argument('start');
        $end = (int) $this->argument('end');

        if ($start > $end) {
            $this->error('Start ID must be less than or equal to End ID.');

            return Command::FAILURE;
        }

        $this->info("Dispatching jobs for store IDs from {$start} to {$end}...");

        $bar = $this->output->createProgressBar($end - $start + 1);

        for ($id = $start; $id <= $end; $id++) {
            CheckStoreJob::dispatch($id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All jobs dispatched successfully!');

        return Command::SUCCESS;
    }
}
