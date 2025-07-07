<?php

namespace DanielCoulbourne\VerbsSync;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EventProcessor
{
    /**
     * Create a new EventProcessor instance.
     *
     * @return void
     */
    public function __construct()
    {
        // No dependencies needed
    }

    /**
     * Process an incoming synced event.
     *
     * @param  array  $event
     * @param  string|null  $sourceUrl
     * @param  string|null  $sourceName
     * @return array
     */
    public function process(array $event, ?string $sourceUrl = null, ?string $sourceName = null)
    {
        try {
            // Extract event data
            $type = $event['type'] ?? null;
            $data = $event['data'] ?? [];
            $id = $event['id'] ?? (string) \Illuminate\Support\Str::uuid();

            if (!$type) {
                Log::error('Cannot process synced event: missing type', ['event' => $event]);
                throw new \Exception('Event type is required');
            }

            // Check if we should skip this event type based on config
            if (!$this->shouldProcessEventType($type)) {
                Log::info("Skipping synced event of type {$type} based on configuration", ['event_id' => $id]);
                throw new \Exception("Event type {$type} is excluded by configuration");
            }

            $metadata = [
                'synced' => true,
                'source_url' => $sourceUrl,
                'source_name' => $sourceName,
                'original_id' => $id,
                'original_created_at' => $event['created_at'] ?? now(),
            ];

            Log::info("Processing synced event of type {$type}", [
                'event_id' => $id,
                'source' => $sourceUrl,
            ]);

            // Directly insert into verbs_events table
            // This is the main purpose of this package - to directly insert events
            // into the verbs_events table without using the Verbs event processing pipeline
            // We're intentionally bypassing Verbs' EventService and inserting directly
            // into the database to avoid any additional processing or validation
            DB::table('verbs_events')->insert([
                'id' => $id,
                'type' => $type,
                'data' => json_encode($data),
                'created_at' => $event['created_at'] ?? now(),
                'updated_at' => now(),
                'metadata' => json_encode($metadata),
            ]);

            return [
                'event_id' => $id,
                'event_type' => $type,
                'event_data' => json_encode($data),
                'source_url' => $sourceUrl,
                'sync_metadata' => json_encode($metadata),
                'synced_at' => now(),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to process synced event: {$e->getMessage()}", [
                'event' => $event,
                'exception' => $e,
            ]);

            throw $e;
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
