<?php

namespace DanielCoulbourne\VerbsSync;

use Illuminate\Support\Facades\Log;
use Thunk\Verbs\Services\EventService;

class EventProcessor
{
    /**
     * The Verbs Event Service.
     *
     * @var \Thunk\Verbs\Services\EventService
     */
    protected $eventService;

    /**
     * Create a new EventProcessor instance.
     *
     * @param  \Thunk\Verbs\Services\EventService  $eventService
     * @return void
     */
    public function __construct(EventService $eventService = null)
    {
        $this->eventService = $eventService ?? app(EventService::class);
    }

    /**
     * Process an incoming synced event.
     *
     * @param  array  $event
     * @param  string  $sourceUrl
     * @return bool
     */
    public function processEvent(array $event, string $sourceUrl)
    {
        try {
            // Extract event data
            $type = $event['type'] ?? null;
            $data = $event['data'] ?? [];
            $metadata = [
                'synced' => true,
                'source_url' => $sourceUrl,
                'original_id' => $event['id'] ?? null,
                'original_created_at' => $event['created_at'] ?? null,
            ];

            if (!$type) {
                Log::error('Cannot process synced event: missing type', ['event' => $event]);
                return false;
            }

            // Check if we should skip this event type based on config
            if (!$this->shouldProcessEventType($type)) {
                Log::info("Skipping synced event of type {$type} based on configuration", ['event_id' => $event['id'] ?? null]);
                return true; // We're skipping intentionally, so return true
            }

            // Process through the Verbs event system
            $result = $this->eventService->dispatch(
                $type,
                $data,
                $metadata
            );

            Log::info("Processed synced event of type {$type}", [
                'event_id' => $event['id'] ?? null,
                'source' => $sourceUrl,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process synced event: {$e->getMessage()}", [
                'event' => $event,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Determine if an event type should be processed.
     *
     * @param  string  $type
     * @return bool
     */
    protected function shouldProcessEventType(string $type)
    {
        $includeEvents = config('verbs-sync.events.include', ['*']);
        $excludeEvents = config('verbs-sync.events.exclude', []);

        // If not in the include list (and include is not wildcard), skip
        if (!in_array('*', $includeEvents) && !in_array($type, $includeEvents)) {
            return false;
        }

        // If in the exclude list, skip
        if (in_array($type, $excludeEvents)) {
            return false;
        }

        return true;
    }
}
