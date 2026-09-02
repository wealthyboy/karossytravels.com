<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AnalyticsEvent extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'event',
        'service',
        'funnel_step',
        'visitor_id',
        'session_id',
        'source',
        'properties',
        'occurred_at',
        'ip_hash',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
