<?php

namespace DanielCoulbourne\VerbsSync\Models;

use Illuminate\Database\Eloquent\Model;

class SyncEvent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verbs_sync_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'source_url',
        'event_type',
        'event_data',
        'sync_metadata',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'event_data' => 'array',
        'sync_metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Scope a query to only include unsynced events.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnsynced($query)
    {
        return $query->whereNull('synced_at');
    }



    /**
     * Scope a query to filter by event type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array  $types
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $types)
    {
        return $query->whereIn('event_type', (array) $types);
    }



    /**
     * Mark this event as synced.
     *
     * @return $this
     */
    public function markAsSynced()
    {
        $this->synced_at = now();
        $this->save();

        return $this;
    }
}
