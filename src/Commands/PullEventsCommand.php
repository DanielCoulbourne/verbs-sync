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
    public function __construct()
    {
        parent::__construct();
        $this->verbsSync = app(VerbsSync::class);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sourceUrl = env('VERBS_SYNC_SOURCE_URL');
        if (!$sourceUrl) {
            $this->error('Source URL not configured. Please set VERBS_SYNC_SOURCE_URL in your .env file.');
            return 1;
        }

        $this->info('Syncing events from source: ' . $sourceUrl);

        // Debug info
        if ($this->option('debug')) {
            $this->info('Debug mode enabled');
            $this->info('Destination URL: ' . env('APP_URL'));
            $this->info('Using Verbs version: ' . (class_exists('\Thunk\Verbs\Verbs') ? \Thunk\Verbs\Verbs::VERSION : 'unknown'));
        }

        $filters = $this->getFiltersFromOptions();

        try {
            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info("DRY RUN MODE - No events will be synced");
            }

            $result = $this->verbsSync->pullEventsFromSource($filters);

            if ($result['success']) {
                $eventsCount = $result['events_count'] ?? 0;

                if ($eventsCount > 0) {
                    if ($dryRun) {
                        $this->info("Found {$eventsCount} events that would be synced");

                        $this->warn('Dry run completed. No events were processed.');
                    } else {
                        $this->info("Successfully synced {$eventsCount} events");

                        // Events are imported but not dispatched automatically
                        // Users can replay them later using Verbs replay functionality
                        if ($this->option('debug')) {
                            $this->info('Note: Events are imported but not dispatched');
                            $this->info('Use Verbs replay functionality to process imported events');
                        }

                        if (isset($result['errors']) && $result['errors'] > 0) {
                            $this->warn("Encountered {$result['errors']} errors during processing");
                        }

                        // Show info about the types of events
                        if (isset($result['event_types']) && !empty($result['event_types'])) {
                            $this->line("Event types processed:");
                            foreach ($result['event_types'] as $type => $count) {
                                $this->line("  - {$type}: {$count}");
                            }
                            $this->warn("Encountered " . count($result['details']['errors']) . " errors during processing");
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
            return 1;
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
            $filters['event_type'] = explode(',', $types);
        }

        // Add debug information if requested
        if ($this->option('debug')) {
            $this->info('Using source URL: ' . env('VERBS_SYNC_SOURCE_URL', 'not set'));
            $this->info('Using API token: ' . (env('VERBS_SYNC_API_TOKEN') ? '[set]' : '[not set]'));
            $this->info('Filter parameters: ' . json_encode($filters));

            // Check if Verbs is available
            if (class_exists('\Thunk\Verbs\Facades\Verbs')) {
                $this->info('Verbs facade is available for replay functionality');
            }
        }

        // Always include a limit to prevent pulling too many events
        $limit = $this->option('limit');
        $filters['limit'] = (int) $limit;

        // Include dry run flag if needed
        if ($this->option('dry-run')) {
            $filters['dry_run'] = true;
        }

        return $filters;
    }
}
