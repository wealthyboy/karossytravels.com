<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HotelBookingSetting extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'cardholder_name',
        'card_brand',
        'expiry_month',
        'expiry_year',
        'vault_reference',
    ];

    protected function casts(): array
    {
        return [
            'cardholder_name' => 'encrypted',
            'card_brand' => 'encrypted',
            'expiry_month' => 'encrypted',
            'expiry_year' => 'encrypted',
            'vault_reference' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate();
    }
}
