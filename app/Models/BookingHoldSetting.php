<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BookingHoldSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'timeout_hours' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => true, 'timeout_hours' => 24]);
    }
}
