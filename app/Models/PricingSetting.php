<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'markup_value' => 'decimal:4'];
    }
}
