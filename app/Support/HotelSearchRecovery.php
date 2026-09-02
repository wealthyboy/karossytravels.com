<?php

namespace App\Support;

use App\Models\HotelSearch;
use Illuminate\Support\Str;

final class HotelSearchRecovery
{
    /** @return array<string, mixed> */
    public static function parameters(HotelSearch $search): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'destination_code' => $search->destination_code,
            'destination_label' => $search->destination_label,
            'check_in' => $search->check_in->toDateString(),
            'check_out' => $search->check_out->toDateString(),
            'adults' => $search->adults,
            'children' => $search->children,
            'rooms' => $search->rooms,
            'currency' => $search->currency,
        ];
    }
}
