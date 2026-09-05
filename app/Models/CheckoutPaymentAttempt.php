<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CheckoutPaymentAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'addon_ids' => 'array',
            'gateway_response' => 'encrypted:array',
            'checkout_payload' => 'encrypted:array',
            'verified_at' => 'datetime',
            'reservation_attempted_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TravelOffer::class, 'travel_offer_id');
    }

    public function hotelOffer(): BelongsTo
    {
        return $this->belongsTo(HotelOffer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
