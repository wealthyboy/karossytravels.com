<?php

namespace App\Travel\Contracts;

interface HotelProvider
{
    public function name(): string;

    /** @param array<string, mixed> $criteria
     *  @return array<int, array<string, mixed>>
     */
    public function search(array $criteria): array;
}
