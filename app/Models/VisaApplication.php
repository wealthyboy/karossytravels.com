<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VisaApplication extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applicants' => 'encrypted:array',
            'contact' => 'encrypted:array',
            'gateway_response' => 'encrypted:array',
            'consultation_added' => 'boolean',
            'paid_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    public function visa(): BelongsTo
    {
        return $this->belongsTo(Visa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
