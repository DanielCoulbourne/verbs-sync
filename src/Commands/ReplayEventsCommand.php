<?php

namespace DanielCoulbourne\VerbsSync\Commands;

use DanielCoulbourne\VerbsSync\EventRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Thunk\Verbs\Facades\Verbs;
use ReflectionClass;

class ReplayEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verbs:sync:replay
                            {--since= : Only replay events since this timestamp (ISO-8601 format)}
                            {--types= : Comma-separated list of event types to replay}
                            {--limit=10 : Maximum number of events to replay per batch}
                            {--dry-run : Show what would be replayed without actually replaying}
                            {--continue : Continue replaying in batches until all events are processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replay imported events from verbs-sync into the local Verbs system';

    /**
     * The EventRepository instance.
     */
    protected $repository;

    /**
     * Create a new command instance.
     */
    public function __construct(EventRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!class_exists('\Thunk\Verbs\Facades\Verbs')) {
            $this->error('Verbs package is not installed. Install it with: composer require hirethunk/verbs');
            return 1;
        }

        $filters = $this->getFiltersFromOptions();
        $dryRun = $this->option('dry-run');
        $continue = $this->option('continue');
        $processed = 0;
        $errors = 0;

        do {
            // Get a batch of events to replay
            $events = $this->repository->getEvents($filters);

            if ($events->isEmpty()) {
                if ($processed === 0) {
                    $this->info('No events found to replay.');
                } else {
                    $this->info("Finished replaying all events. Total: {$processed}");
                }
                break;
            }

            $this->info("Found " . $events->count() . " events to replay" . ($dryRun ? " (dry run)" : "") . "...");

            if ($dryRun) {
                $this->table(
                    ['Event ID', 'Event Type', 'Created At'],
                    $events->map(function ($event) {
                        return [
                            $event->event_id,
                            $event->event_type,
                            $event->created_at,
                        ];
                    })->toArray()
                );
                break;
            }

            // Process each event
            foreach ($events as $event) {
                $eventType = $event->event_type;
                $eventData = json_decode($event->event_data, true);

                try {
                    $this->line("Replaying {$eventType} ({$event->event_id})...");

                    if (!class_exists($eventType)) {
                        $this->warn("  ⚠ Event class {$eventType} does not exist, skipping");
                        continue;
                    }

                    // Use reflection to instantiate and fire the event with Verbs
                    $reflection = new ReflectionClass($eventType);

                    if (!is_subclass_of($eventType, '\Thunk\Verbs\Event')) {
                        $this->warn("  ⚠ {$eventType} is not a Verbs event, skipping");
                        continue;
                    }

                    // For Verbs events, we need to prepare parameters for the fire method
                    if ($reflection->hasMethod('handle')) {
                        $handleMethod = $reflection->getMethod('handle');
                        $parameters = $handleMethod->getParameters();

                        $namedParams = [];
                        foreach ($parameters as $param) {
                            $paramName = $param->getName();
                            if (isset($eventData[$paramName])) {
                                $namedParams[$paramName] = $eventData[$paramName];
                            } else {
                                // If parameter doesn't match event data properties, add null or default
                                $namedParams[$paramName] = $param->isDefaultValueAvailable()
                                    ? $param->getDefaultValue()
                                    : null;
                            }
                        }

                        // Call the static fire method with the correct named parameters
                        $fireMethod = $reflection->getMethod('fire');
                        $fireMethod->invokeArgs(null, $namedParams);

                        // Mark as processed
                        $processed++;
                        $this->line("  ✓ Replayed successfully");

                        // Optionally update the event to mark it as replayed
                        DB::table('verbs_sync_events')
                            ->where('id', $event->id)
                            ->update(['replayed_at' => now()]);
                    } else {
                        $this->warn("  ⚠ {$eventType} does not have a handle method, skipping");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("  ✗ Error replaying event: " . $e->getMessage());
                    Log::error("Error replaying event: {$e->getMessage()}", [
                        'event_id' => $event->event_id,
                        'event_type' => $eventType,
                        'exception' => $e,
                    ]);
                }
            }

            // Commit all Verbs events
            try {
                Verbs::commit();
                $this->info("Committed {$processed} events to Verbs");
            } catch (\Exception $e) {
                $this->error("Error committing events: " . $e->getMessage());
                Log::error("Error committing events: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
                return 1;
            }

            // Update the filter to get the next batch
            if ($continue && !$events->isEmpty()) {
                $lastEvent = $events->last();
                $filters['since'] = $lastEvent->created_at;
                $this->line("Getting next batch since {$lastEvent->created_at}...");
            }

        } while ($continue && !$events->isEmpty());

        if ($errors > 0) {
            $this->warn("Completed with {$errors} errors. Check logs for details.");
            return 1;
        }

        $this->info("Replay completed successfully! {$processed} events processed.");
        return 0;
    }

    /**
     * Get filters from command options.
     */
    protected function getFiltersFromOptions(): array
    {
        $filters = [];

        if ($since = $this->option('since')) {
            $filters['since'] = $since;
        }

        if ($types = $this->option('types')) {
            $filters['event_type'] = explode(',', $types);
        }

        // Always include a limit to prevent processing too many events
        $limit = $this->option('limit');
        $filters['limit'] = (int) $limit;

        return $filters;
    }
}
