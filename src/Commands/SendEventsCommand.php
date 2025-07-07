<?php

namespace DanielCoulbourne\VerbsSync\Commands;

use DanielCoulbourne\VerbsSync\VerbsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verbs:sync:send
                            {--since= : Only send events since this timestamp (ISO-8601 format)}
                            {--types= : Comma-separated list of event types to send}
                            {--limit=10 : Maximum number of events to send}
                            {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send events to a remote Verbs destination application';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!env('VERBS_SYNC_DESTINATION_URL')) {
            $this->error('Destination URL not configured. Please set VERBS_SYNC_DESTINATION_URL in your .env file.');
            return 1;
        }

        if (!env('VERBS_SYNC_API_KEY')) {
            $this->error('API key not configured. Please set VERBS_SYNC_API_KEY in your .env file.');
            return 1;
        }

        $this->info('Sending events to destination: ' . env('VERBS_SYNC_DESTINATION_URL'));

        $filters = $this->getFiltersFromOptions();
        $verbsSync = app(VerbsSync::class);

        try {
            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info("DRY RUN MODE - No events will be sent");
                return 0;
            }

            $result = $verbsSync->sendEvents($filters);

            if ($result['success']) {
                $eventsCount = $result['events_count'] ?? 0;

                if ($eventsCount > 0) {
                    $this->info("Successfully sent {$eventsCount} events");

                    if (isset($result['response']) && is_array($result['response'])) {
                        $this->line("Response: " . json_encode($result['response'], JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->line('No events to send to destination.');
                }

                return 0;
            } else {
                $this->error('Failed to send events: ' . ($result['message'] ?? 'Unknown error'));
                Log::error('Failed to send events: ' . json_encode($result));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error sending events: ' . $e->getMessage());
            Log::error('Error in send command: ' . $e->getMessage(), [
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

        // Always include a limit to prevent sending too many events
        $limit = $this->option('limit');
        $filters['limit'] = (int) $limit;

        return $filters;
    }
}
