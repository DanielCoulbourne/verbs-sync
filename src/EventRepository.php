<?php

namespace DanielCoulbourne\VerbsSync;

use Illuminate\Support\Collection;
use Thunk\Verbs\Models\Event;
use Illuminate\Database\Eloquent\Builder;

class EventRepository
{
    /**
     * Get events from the source app that should be synced.
     *
     * @param  array  $filters
     * @return \Illuminate\Support\Collection
     */
    public function getSourceEvents(array $filters = [])
    {
        // This method is for processing locally fetched events
        // from the remote source application's API response

        $query = Event::query();

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Apply configuration-based filters
        $query = $this->applyConfigFilters($query);

        // Get the events
        $events = $query->get();

        // Format events for syncing
        return $this->formatEvents($events);
    }

    /**
     * Apply user-provided filters to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyFilters(Builder $query, array $filters)
    {
        // Filter by creation time
        if (isset($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }

        // Filter by event types
        if (isset($filters['type']) && is_array($filters['type'])) {
            $query->whereIn('type', $filters['type']);
        }

        // Filter by IDs
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        // Apply limit
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        } else {
            // Default batch size from config
            $batchSize = config('verbs-sync.options.batch_size', 100);
            $query->limit($batchSize);
        }

        // Order by creation time
        $query->orderBy('created_at', 'asc');

        return $query;
    }

    /**
     * Apply configuration-based filters to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyConfigFilters(Builder $query)
    {
        $includeEvents = config('verbs-sync.events.include', ['*']);
        $excludeEvents = config('verbs-sync.events.exclude', []);

        // Include specific event types (if not wildcard)
        if (!in_array('*', $includeEvents)) {
            $query->whereIn('type', $includeEvents);
        }

        // Exclude specific event types
        if (!empty($excludeEvents)) {
            $query->whereNotIn('type', $excludeEvents);
        }

        // Only get events that haven't been synced yet
        // This requires tracking which events have been synced, which could be
        // implemented through a relationship or separate tracking table

        return $query;
    }

    /**
     * Format events for syncing.
     *
     * @param  \Illuminate\Support\Collection  $events
     * @return \Illuminate\Support\Collection
     */
    protected function formatEvents(Collection $events)
    {
        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->type,
                'data' => $event->data,
                'created_at' => $event->created_at->toIso8601String(),
                'source_url' => config('app.url'),
            ];
        });
    }

    /**
     * Find a specific event by ID.
     *
     * @param  string  $eventId
     * @return array|null
     */
    public function findEvent($eventId)
    {
        $event = Event::find($eventId);

        if (!$event) {
            return null;
        }

        return [
            'id' => $event->id,
            'type' => $event->type,
            'data' => $event->data,
            'created_at' => $event->created_at->toIso8601String(),
            'source_url' => config('app.url'),
        ];
    }
}
