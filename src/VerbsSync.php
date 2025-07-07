<?php

namespace DanielCoulbourne\VerbsSync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerbsSync
{
    /**
     * The event processor instance.
     */
    protected EventProcessor $processor;

    /**
     * The event repository instance.
     */
    protected EventRepository $repository;

    /**
     * Create a new VerbsSync instance.
     */
    public function __construct(EventProcessor $processor, EventRepository $repository)
    {
        $this->processor = $processor;
        $this->repository = $repository;
    }

    /**
     * Pull events from the configured source.
     *
     * @param  array  $filters
     * @return array
     */
    public function pullEventsFromSource(array $filters = []): array
    {
        $sourceUrl = env('VERBS_SYNC_SOURCE_URL');
        $apiToken = env('VERBS_SYNC_API_TOKEN');

        if (!$sourceUrl) {
            return [
                'success' => false,
                'message' => 'Source URL not configured',
            ];
        }

        try {
            // Add API token to request header
            $response = Http::withHeader('Authorization', 'Bearer ' . $apiToken)
                ->timeout(30)
                ->get($sourceUrl, $filters);

            if ($response->successful()) {
                $result = $response->json();
                $events = collect($result['events'] ?? []);

                if ($events->isEmpty()) {
                    return [
                        'success' => true,
                        'message' => 'No new events to pull',
                        'events_count' => 0,
                    ];
                }

                // Process events
                $processed = [];
                $processedCount = 0;
                $errorCount = 0;
                $eventTypes = [];

                foreach ($events as $event) {
                    try {
                        $processedEvent = $this->processor->process($event, $sourceUrl);
                        $this->repository->store($processedEvent);
                        $processedCount++;

                        // Track event types for reporting
                        $eventType = $processedEvent['event_type'];
                        if (!isset($eventTypes[$eventType])) {
                            $eventTypes[$eventType] = 0;
                        }
                        $eventTypes[$eventType]++;
                    } catch (\Exception $e) {
                        Log::error("Failed to process event: {$e->getMessage()}", ['event' => $event]);
                        $errorCount++;
                    }
                }

                // Record activity
                DB::table('verbs_sync_logs')->insert([
                    'operation' => 'pull_events',
                    'status' => 'success',
                    'details' => json_encode([
                        'processed' => $processedCount,
                        'errors' => $errorCount,
                    ]),
                    'events_count' => $processedCount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => "Successfully pulled and processed {$processedCount} events",
                    'events_count' => $processedCount,
                    'errors' => $errorCount,
                    'event_types' => $eventTypes,
                ];
            }

            // Record failed activity
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'pull_events',
                'status' => 'failed',
                'details' => json_encode(['error' => $response->body()]),
                'events_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to pull events: ' . $response->status(),
                'details' => $response->body(),
            ];
        } catch (\Exception $e) {
            // Record error
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'pull_events',
                'status' => 'error',
                'details' => json_encode(['exception' => $e->getMessage()]),
                'events_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Error pulling events: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send events to a destination.
     *
     * @param  array  $filters
     * @return array
     */
    public function sendEvents(array $filters = []): array
    {
        $destinationUrl = env('VERBS_SYNC_DESTINATION_URL');
        $apiKey = env('VERBS_SYNC_API_KEY');
        $appName = env('VERBS_SYNC_APP_NAME', config('app.name'));

        if (!$destinationUrl) {
            return [
                'success' => false,
                'message' => 'Destination URL not configured',
            ];
        }

        try {
            // Get events to send
            $query = DB::table('verbs_sync_events');

            if (!empty($filters['event_type'])) {
                $query->where('event_type', $filters['event_type']);
            }

            if (!empty($filters['since'])) {
                $query->where('created_at', '>=', $filters['since']);
            }

            $events = $query->limit($filters['limit'] ?? 10)->get();

            if ($events->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No events to send',
                    'events_count' => 0,
                ];
            }

            // Format events for sending
            $formattedEvents = $events->map(function ($event) {
                return [
                    'id' => $event->event_id,
                    'type' => $event->event_type,
                    'data' => json_decode($event->event_data, true),
                    'created_at' => $event->created_at,
                ];
            })->toArray();

            // Send to destination
            $response = Http::withHeader('X-Verbs-Sync-Key', $apiKey)
                ->timeout(30)
                ->post($destinationUrl, [
                    'events' => $formattedEvents,
                    'source_url' => config('app.url'),
                    'source_name' => $appName,
                ]);

            if ($response->successful()) {
                // Record successful activity
                DB::table('verbs_sync_logs')->insert([
                    'operation' => 'send_events',
                    'status' => 'success',
                    'details' => json_encode($response->json()),
                    'events_count' => count($formattedEvents),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => "Successfully sent " . count($formattedEvents) . " events",
                    'events_count' => count($formattedEvents),
                    'response' => $response->json(),
                ];
            }

            // Record failed activity
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'send_events',
                'status' => 'failed',
                'details' => json_encode(['error' => $response->body()]),
                'events_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send events: ' . $response->status(),
                'details' => $response->body(),
            ];
        } catch (\Exception $e) {
            // Record error
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'send_events',
                'status' => 'error',
                'details' => json_encode(['exception' => $e->getMessage()]),
                'events_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Error sending events: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get sync status information.
     *
     * @return array
     */
    public function getSyncStatus(): array
    {
        $lastPull = DB::table('verbs_sync_logs')
            ->where('operation', 'pull_events')
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->first();

        $lastSend = DB::table('verbs_sync_logs')
            ->where('operation', 'send_events')
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->first();

        $totalEvents = DB::table('verbs_sync_events')->count();

        return [
            'last_pull' => $lastPull ? [
                'timestamp' => $lastPull->created_at,
                'events_count' => $lastPull->events_count,
            ] : null,
            'last_send' => $lastSend ? [
                'timestamp' => $lastSend->created_at,
                'events_count' => $lastSend->events_count,
            ] : null,
            'total_synced_events' => $totalEvents,
            'sync_type' => env('VERBS_SYNC_TYPE', 'destination'),
            'app_name' => env('VERBS_SYNC_APP_NAME', config('app.name')),
        ];
    }
}
