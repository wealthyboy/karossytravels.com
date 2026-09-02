<?php

namespace App\Support;

final class HotelImageDefaults
{
    public static function path(string $seed, int $offset = 0): string
    {
        $hash = (int) sprintf('%u', crc32(mb_strtolower(trim($seed))));
        $index = (($hash + $offset) % 26) + 1;

        return "images/AM1/{$index}.jpg";
    }
}
