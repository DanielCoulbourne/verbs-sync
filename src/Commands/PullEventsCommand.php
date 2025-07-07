<?php

namespace DanielCoulbourne\VerbsSync\Commands;

use DanielCoulbourne\VerbsSync\VerbsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PullEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verbs:sync
                            {--since= : Only pull events since this timestamp (ISO-8601 format)}
                            {--types= : Comma-separated list of event types to pull}
                            {--limit=100 : Maximum number of events to pull}
                            {--dry-run : Show what would be synced without actually syncing}
                            {--debug : Show additional debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync events from a remote Verbs source application';

    /**
     * The VerbsSync instance.
     *
     * @var \DanielCoulbourne\VerbsSync\VerbsSync
     */
    protected $verbsSync;

    /**
     * Create a new command instance.
     *
     * @param  \DanielCoulbourne\VerbsSync\VerbsSync  $verbsSync
     * @return void
     */
    public function __construct(VerbsSync $verbsSync)
    {
        parent::__construct();
        $this->verbsSync = $verbsSync;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!env('VERBS_SYNC_SOURCE_URL')) {
            $this->error('Source URL not configured. Please set VERBS_SYNC_SOURCE_URL in your .env file.');
            return Command::FAILURE;
        }

        $this->info('Syncing events from source: ' . env('VERBS_SYNC_SOURCE_URL'));

        $filters = $this->getFiltersFromOptions();
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info("DRY RUN MODE - No events will be synced");
            $filters['dry_run'] = true;
        }

        try {
            $result = $this->verbsSync->pullEventsFromSource($filters);

            if ($result['success']) {
                $eventsCount = $result['events_count'] ?? 0;

                if ($eventsCount > 0) {
                    if ($dryRun) {
                        $this->info("Found {$eventsCount} events that would be synced");

                        // Show sample of events that would be synced
                        if (isset($result['events']) && is_array($result['events'])) {
                            $this->table(
                                ['ID', 'Type', 'Created At'],
                                collect($result['events'])->take(5)->map(function ($event) {
                                    return [
                                        $event['id'] ?? 'N/A',
                                        $event['type'] ?? 'N/A',
                                        $event['created_at'] ?? 'N/A',
                                    ];
                                })->toArray()
                            );

                            if (count($result['events']) > 5) {
                                $this->line("...and " . (count($result['events']) - 5) . " more");
                            }
                        }
                    } else {
                        $this->info("Successfully synced {$eventsCount} events");

                        if (isset($result['details']) && is_array($result['details'])) {
                            $this->line("Processed: {$result['details']['processed']} events");
                            $this->line("Skipped: {$result['details']['skipped']} events (already synced)");

                            if (!empty($result['details']['errors'])) {
                                $this->warn("Encountered " . count($result['details']['errors']) . " errors during processing");
                            }
                        }
                    }

                    // Show event types if available and debug is enabled
                    if ($this->option('debug') && isset($result['event_types'])) {
                        $this->info("Event types:");
                        foreach ($result['event_types'] as $type => $count) {
                            $this->line("  - {$type}: {$count}");
                        }
                    }
                } else {
                    $this->line('No new events to sync from source.');
                }

                return Command::SUCCESS;
            } else {
                $this->error('Failed to sync events: ' . ($result['message'] ?? 'Unknown error'));
                Log::error('Failed to sync events: ' . json_encode($result));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error syncing events: ' . $e->getMessage());
            Log::error('Error in sync command: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Get filters from command options.
     *
     * @return array
     */
    protected function getFiltersFromOptions()
    {
        $filters = [];

        if ($since = $this->option('since')) {
            $filters['since'] = $since;
        }

        if ($types = $this->option('types')) {
            $filters['type'] = explode(',', $types);
        }

        // Always include a limit to prevent pulling too many events
        $limit = $this->option('limit');
        $filters['limit'] = (int) $limit;

        return $filters;
    }
}
