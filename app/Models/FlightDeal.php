<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FlightDeal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'travel_from' => 'date',
            'travel_until' => 'date',
            'discount_value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }
}
