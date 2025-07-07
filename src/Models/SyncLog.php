<?php

namespace DanielCoulbourne\VerbsSync\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verbs_sync_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'operation',
        'status',
        'destination_id',
        'details',
        'events_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'details' => 'array',
        'events_count' => 'integer',
    ];

    // No destination relationship needed in the simplified version

    /**
     * Scope a query to only include logs with a specific operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array  $operations
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithOperation($query, $operations)
    {
        return $query->whereIn('operation', (array) $operations);
    }

    /**
     * Scope a query to only include logs with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array  $statuses
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $statuses)
    {
        return $query->whereIn('status', (array) $statuses);
    }

    /**
     * Scope a query to only include successful logs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed logs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'error']);
    }

    /**
     * Check if the log was successful.
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->status === 'success';
    }

    /**
     * Check if the log failed.
     *
     * @return bool
     */
    public function isFailed()
    {
        return in_array($this->status, ['failed', 'error']);
    }

    /**
     * Get a human-readable description of this log entry.
     *
     * @return string
     */
    public function getDescription()
    {
        $eventCount = $this->events_count > 0 ? "{$this->events_count} events" : "no events";

        if ($this->operation === 'pull') {
            return "Pull {$eventCount} from source: {$this->status}";
        }

        return "{$this->operation} operation: {$this->status}";
    }
}
