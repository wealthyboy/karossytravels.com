<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BookingHoldSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'timeout_hours' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => true, 'timeout_hours' => 24]);
    }

    /**
     * @return array{currency: string, bank_name: ?string, account_name: ?string, account_number: ?string, sort_code: ?string}
     */
    public function bankDetailsFor(string $currency): array
    {
        $currency = strtoupper($currency);
        $prefix = match ($currency) {
            'NGN' => 'ngn',
            'USD' => 'usd',
            default => null,
        };

        if ($prefix === null) {
            return [
                'currency' => $currency,
                'bank_name' => null,
                'account_name' => null,
                'account_number' => null,
                'sort_code' => null,
            ];
        }

        return [
            'currency' => $currency,
            'bank_name' => $this->getAttribute("{$prefix}_bank_name")
                ?: ($prefix === 'ngn' ? $this->bank_name : null),
            'account_name' => $this->getAttribute("{$prefix}_account_name")
                ?: ($prefix === 'ngn' ? $this->account_name : null),
            'account_number' => $this->getAttribute("{$prefix}_account_number")
                ?: ($prefix === 'ngn' ? $this->account_number : null),
            'sort_code' => $this->getAttribute("{$prefix}_sort_code")
                ?: ($prefix === 'ngn' ? $this->sort_code : null),
        ];
    }
}
