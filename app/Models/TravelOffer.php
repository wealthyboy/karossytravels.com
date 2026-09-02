<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TravelOffer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['provider_reference'];

    protected function casts(): array
    {
        return [
            'itinerary' => 'array',
            'fare_summary' => 'array',
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
        ];
    }

    public function flightSearch(): BelongsTo
    {
        return $this->belongsTo(FlightSearch::class);
    }
}
