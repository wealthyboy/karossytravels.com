<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class FlightOffer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereDate('departure_date', '>=', today())
            ->orderBy('sort_order')
            ->orderBy('departure_date');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->image_url;
    }
}
