<?php

namespace App\Travel\Contracts;

interface FlightProvider
{
    /**
     * @param array<string, mixed> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria): array;

    public function name(): string;
}
