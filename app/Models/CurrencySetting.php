<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CurrencySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'manual_rate' => 'decimal:6', 'adjustment_percent' => 'decimal:4'];
    }
}
