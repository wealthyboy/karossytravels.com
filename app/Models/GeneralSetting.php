<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class GeneralSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['phone_numbers' => 'array', 'social_links' => 'array'];
    }

    public static function current(): self
    {
        $defaults = new self([
            'site_name' => config('app.name', 'Karossy Travels'),
            'contact_email' => config('travel.support.email', 'info@karossytravels.com'),
            'phone_numbers' => array_values(array_filter([config('travel.support.phone')])),
            'whatsapp_number' => config('travel.support.whatsapp'),
            'social_links' => [],
        ]);

        // Keep public pages available while an additive deployment migration is pending.
        if (! Schema::hasTable('general_settings')) {
            return $defaults;
        }

        return self::query()->find(1) ?? $defaults;
    }
}
