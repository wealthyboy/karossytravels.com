<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Customer extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['passport_number'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expires_at' => 'date',
            'passport_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->title, $this->first_name, $this->middle_name, $this->last_name])->filter()->join(' '));
    }
}
