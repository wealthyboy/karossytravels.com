<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FlightSearch extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['departure_date' => 'date', 'return_date' => 'date', 'segments' => 'array'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TravelOffer::class);
    }
}
