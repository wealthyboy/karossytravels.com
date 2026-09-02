<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class FairRule extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_karossey_rule' => 'boolean',
        'active' => 'boolean',
    ];

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()));
    }

    public function scopeForAirline(Builder $query, string $airlineCode): Builder
    {
        return $query->where(function (Builder $query) use ($airlineCode): void {
            $query->where('is_karossey_rule', true)
                ->orWhere('airline_code', strtoupper($airlineCode));
        });
    }
}
