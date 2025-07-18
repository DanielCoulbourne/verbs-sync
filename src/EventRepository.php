<?php

namespace DanielCoulbourne\VerbsSync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EventRepository
{
    /**
     * Store a processed event.
     *
     * @param  array  $eventData
     * @return array
     */
    public function store(array $eventData): array
    {
        try {
            // Check if replayed_at column exists, if not add it
            if (!Schema::hasColumn('verbs_sync_events', 'replayed_at')) {
                Schema::table('verbs_sync_events', function ($table) {
                    $table->timestamp('replayed_at')->nullable();
                });
            }

            // Insert into the verbs_sync_events table for tracking
            $id = DB::table('verbs_sync_events')->insertGetId([
                'event_id' => $eventData['event_id'],
                'source_url' => $eventData['source_url'],
                'event_type' => $eventData['event_type'],
                'event_data' => $eventData['event_data'],
                'sync_metadata' => $eventData['sync_metadata'] ?? null,
                'synced_at' => $eventData['synced_at'] ?? now(),
                'replayed_at' => null, // New events have not been replayed
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Also insert directly into verbs_events table
            // This is the main purpose of this package - to directly insert events
            // into the verbs_events table without using the Verbs event processing pipeline
            // We're intentionally bypassing Verbs' EventService and inserting directly
            // into the database to avoid any additional processing or validation
            DB::table('verbs_events')->insert([
                'id' => $eventData['event_id'],
                'type' => $eventData['event_type'],
                'data' => $eventData['event_data'],
                'created_at' => now(),
                'updated_at' => now(),
                'metadata' => json_encode([
                    'synced' => true,
                    'source_url' => $eventData['source_url'],
                    'original_id' => $eventData['event_id'],
                ]),
            ]);

            Log::info("Stored synced event directly in verbs_events table", [
                'event_id' => $eventData['event_id'],
                'event_type' => $eventData['event_type'],
            ]);

            // Record in log table
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'store_event',
                'status' => 'success',
                'details' => json_encode([
                    'event_id' => $eventData['event_id'],
                    'event_type' => $eventData['event_type'],
                ]),
                'events_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $eventData;
        } catch (\Exception $e) {
            Log::error("Failed to store synced event: {$e->getMessage()}", [
                'event' => $eventData,
                'exception' => $e,
            ]);

            // Record in log table
            DB::table('verbs_sync_logs')->insert([
                'operation' => 'store_event',
                'status' => 'error',
                'details' => json_encode([
                    'error' => $e->getMessage(),
                    'event_id' => $eventData['event_id'] ?? 'unknown',
                ]),
                'events_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Find an event by its ID.
     *
     * @param  string  $eventId
     * @return array|null
     */
    public function find(string $eventId): ?array
    {
        return DB::table('verbs_sync_events')
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * Get all events with optional filtering.
     *
     * @param  array  $filters
     * @return \Illuminate\Support\Collection
     */
    public function getEvents(array $filters = []): Collection
    {
        $query = DB::table('verbs_sync_events');

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }

        // Filter by replay status
        if (isset($filters['replayed']) && Schema::hasColumn('verbs_sync_events', 'replayed_at')) {
            if ($filters['replayed'] === true) {
                $query->whereNotNull('replayed_at');
            } elseif ($filters['replayed'] === false) {
                $query->whereNull('replayed_at');
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();
    }

    /**
     * Mark an event as replayed.
     *
     * @param  string  $eventId
     * @return bool
     */
    public function markAsReplayed(string $eventId): bool
    {
        try {
            // Check if replayed_at column exists
            if (!Schema::hasColumn('verbs_sync_events', 'replayed_at')) {
                Schema::table('verbs_sync_events', function ($table) {
                    $table->timestamp('replayed_at')->nullable();
                });
            }

            DB::table('verbs_sync_events')
                ->where('event_id', $eventId)
                ->update([
                    'replayed_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to mark event as replayed: {$e->getMessage()}", [
                'event_id' => $eventId,
                'exception' => $e,
            ]);

            return false;
        }
    }
}
