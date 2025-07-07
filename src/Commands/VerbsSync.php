<?php

namespace DanielCoulbourne\VerbsSync\Commands;

use Illuminate\Console\Command;

class VerbsSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verbs-sync:dummy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dummy command to satisfy dependency injection';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('This is a dummy command that exists only for testing purposes.');
        return 0;
    }
}
