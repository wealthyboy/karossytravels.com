<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Booking extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['travellers' => 'encrypted:array', 'details' => 'array', 'booked_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function travelOffer(): BelongsTo
    {
        return $this->belongsTo(TravelOffer::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(BookingAction::class)->latest();
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'booking_addon')
            ->withPivot(['quantity', 'price_cents', 'currency'])
            ->withTimestamps();
    }
}
