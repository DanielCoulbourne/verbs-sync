<?php

namespace DanielCoulbourne\VerbsSync;

use DanielCoulbourne\VerbsSync\Models\SyncEvent;
use DanielCoulbourne\VerbsSync\Models\SyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerbsSync
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Create a new VerbsSync instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Pull events from the configured source.
     *
     * @param  array  $filters
     * @param  \Illuminate\Http\Client\Response|null  $testResponse  For testing purposes
     * @return array
     */
    public function pullEventsFromSource(array $filters = [], $testResponse = null)
    {
        $sourceUrl = config('verbs-sync.source.url');
        $apiToken = config('verbs-sync.source.api_token');

        if (! $sourceUrl) {
            return [
                'success' => false,
                'message' => 'Source URL not configured',
            ];
        }

        try {
            if ($testResponse) {
                $response = $testResponse;
            } else {
                $response = Http::withToken($apiToken)
                    ->timeout(30)
                    ->get($sourceUrl . '/api/verbs/events', $filters);
            }

            if ($response->successful()) {
                $result = $response->json();
                $events = collect($result['data'] ?? []);

                if ($events->isEmpty()) {
                    return [
                        'success' => true,
                        'message' => 'No new events to pull',
                        'events_count' => 0,
                    ];
                }

                // Process and store the pulled events
                $processed = $this->processIncomingEvents($events, $sourceUrl);

                // Include events in the result for dry run mode
                if (isset($filters['dry_run']) && $filters['dry_run']) {
                    $result['events'] = $events;
                }

                $this->recordSyncActivity(
                    'pull',
                    'success',
                    $events->count(),
                    ['processed' => $processed]
                );

                return [
                    'success' => true,
                    'message' => "Successfully pulled and processed {$events->count()} events",
                    'events_count' => $events->count(),
                    'details' => $processed,
                ];
            }

            $this->recordSyncActivity(
                'pull',
                'failed',
                0,
                ['error' => $response->body()]
            );

            return [
                'success' => false,
                'message' => 'Failed to pull events: ' . $response->status(),
                'details' => $response->body(),
            ];
        } catch (\Exception $e) {
            $this->recordSyncActivity(
                'pull',
                'error',
                0,
                ['exception' => $e->getMessage()]
            );

            return [
                'success' => false,
                'message' => 'Error pulling events: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process incoming events from a source.
     *
     * @param  \Illuminate\Support\Collection  $events
     * @param  string  $sourceUrl
     * @return array
     */
    protected function processIncomingEvents(Collection $events, string $sourceUrl)
    {
        $results = [
            'success' => true,
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($events as $event) {
            try {
                // Check if event is already processed
                $existingEvent = SyncEvent::where('event_id', $event['id'])
                    ->where('source_url', $sourceUrl)
                    ->first();

                if ($existingEvent) {
                    $results['skipped']++;
                    continue;
                }

                // Create a new sync event record
                $syncEvent = new SyncEvent([
                    'event_id' => $event['id'],
                    'source_url' => $sourceUrl,
                    'event_type' => $event['type'],
                    'event_data' => $event['data'],
                    'sync_metadata' => [
                        'pulled_at' => now()->toIso8601String(),
                        'source_url' => $sourceUrl,
                    ],
                    'synced_at' => now(),
                ]);

                $syncEvent->save();

                // Process the event through Verbs
                $eventProcessor = app(EventProcessor::class);
                $success = $eventProcessor->processEvent($event, $sourceUrl);

                if (!$success) {
                    throw new \Exception("Failed to process event ID: {$event['id']}");
                }

                $results['processed']++;
            } catch (\Exception $e) {
                $results['success'] = false;
                $results['errors'][] = [
                    'event_id' => $event['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                Log::error("Error processing synced event: {$e->getMessage()}", [
                    'event' => $event,
                    'exception' => $e,
                ]);
            }
        }

        return $results;
    }

    /**
     * Record sync activity for logging and monitoring.
     *
     * @param  string  $operation
     * @param  string  $status
     * @param  int  $eventsCount
     * @param  array  $details
     * @return \DanielCoulbourne\VerbsSync\Models\SyncLog
     */
    protected function recordSyncActivity($operation, $status, $eventsCount = 0, array $details = [])
    {
        return SyncLog::create([
            'operation' => $operation,
            'status' => $status,
            'events_count' => $eventsCount,
            'details' => $details,
        ]);
    }

    /**
     * Get sync status information.
     *
     * @return array
     */
    public function getSyncStatus()
    {
        $lastPull = SyncLog::where('operation', 'pull')
            ->where('status', 'success')
            ->latest()
            ->first();

        return [
            'last_pull' => $lastPull ? [
                'timestamp' => $lastPull->created_at->toIso8601String(),
                'events_count' => $lastPull->events_count,
            ] : null,
            'total_synced_events' => SyncEvent::whereNotNull('synced_at')->count(),
        ];
    }
}
