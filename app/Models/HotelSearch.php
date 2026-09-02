<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class HotelSearch extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(HotelOffer::class);
    }
}
