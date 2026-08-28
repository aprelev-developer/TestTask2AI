<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model for `detection_events` — the append-only journal.
 * Kept to persistence concerns only; only `created_at` is tracked (no
 * `updated_at`, rows are never modified after being written).
 */
class DetectionEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'run_id',
        'request_id',
        'result',
        'triggered_scenarios',
        'details',
        'incomplete_checks',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_scenarios' => 'array',
            'details' => 'array',
            'incomplete_checks' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
