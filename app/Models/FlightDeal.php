<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FlightDeal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'travel_from' => 'date',
            'travel_until' => 'date',
            'booking_from' => 'date',
            'booking_until' => 'date',
            'all_airlines' => 'boolean',
            'airline_codes' => 'array',
            'discount_value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function getAirlineSummaryAttribute(): string
    {
        if ($this->all_airlines) {
            return 'All airlines';
        }
        if ($this->airline_codes) {
            return Airline::whereIn('code', $this->airline_codes)->orderBy('name')->pluck('name')->implode(', ');
        }

        return $this->airline?->name ?? 'No airline selected';
    }

    public function homepageOffers(): HasMany
    {
        return $this->hasMany(FlightOffer::class, 'flight_deal_id');
    }
}
