<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Visa extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'fee_cents' => 'integer',
        'consultation_fee_cents' => 'integer',
        'requirements_list' => 'array',
        'important_information' => 'array',
        'active' => 'boolean',
        'featured' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(VisaApplication::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
