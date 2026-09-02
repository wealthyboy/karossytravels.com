<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Addon extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'integer',
        'active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_addon')->withPivot(['quantity', 'price_cents', 'currency']);
    }
}
