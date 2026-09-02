<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelOffer extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $hidden = ['rate_key'];

    protected function casts(): array
    {
        return [
            'rating' => 'float', 'location' => 'array', 'amenities' => 'array',
            'pricing' => 'array', 'refundable' => 'boolean',
            'breakfast_included' => 'boolean', 'expires_at' => 'datetime',
        ];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(HotelSearch::class, 'hotel_search_id');
    }
}
