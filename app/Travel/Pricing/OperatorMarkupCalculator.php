<?php

namespace App\Travel\Pricing;

final class OperatorMarkupCalculator
{
    /** @return array{type: ?string, value: ?float, amount_minor: int} */
    public function calculate(int $baseMinor, ?string $type, mixed $value): array
    {
        $type = in_array($type, ['fixed', 'percentage'], true) ? $type : null;
        $numericValue = $type ? max(0, (float) $value) : null;
        $amount = match ($type) {
            'fixed' => (int) round($numericValue * 100),
            'percentage' => (int) round($baseMinor * ($numericValue / 100)),
            default => 0,
        };

        return ['type' => $type, 'value' => $numericValue, 'amount_minor' => $amount];
    }
}
