<?php

namespace App\Travel\Pricing;

use App\Models\PricingSetting;

final class PercentagePriceAdjustment
{
    /** @return array{type:string,percent:float,markup_minor:int,markdown_minor:int,total_minor:int} */
    public function calculate(int $baseMinor, ?PricingSetting $setting): array
    {
        $unchanged = ['type' => 'none', 'percent' => 0.0, 'markup_minor' => 0, 'markdown_minor' => 0, 'total_minor' => $baseMinor];
        $percent = (float) ($setting?->markup_value ?? 0);
        $type = $setting?->adjustment_type ?? 'markup';

        // Never reinterpret an old currency amount as a percentage. Disabled,
        // blank and zero rules also leave the supplier price unchanged.
        if (! $setting?->enabled || $setting->markup_type !== 'percentage'
            || ! is_finite($percent) || $percent <= 0 || $percent > 1000000
            || ! in_array($type, ['markup', 'markdown'], true)
            || ($type === 'markdown' && $percent >= 100)) {
            return $unchanged;
        }

        $markup = $type === 'markup' ? (int) ceil($baseMinor * $percent / 100) : 0;
        $markdown = $type === 'markdown' ? (int) floor($baseMinor * $percent / 100) : 0;

        return ['type' => $type, 'percent' => $percent, 'markup_minor' => $markup, 'markdown_minor' => $markdown, 'total_minor' => $baseMinor + $markup - $markdown];
    }
}
