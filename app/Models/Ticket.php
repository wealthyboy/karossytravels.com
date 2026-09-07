<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Ticket extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'voided_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    /**
     * A ticket is only issued when the supplier has returned a real e-ticket
     * document number and the local status has been persisted as issued.
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued' && trim((string) $this->ticket_number) !== '';
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query
            ->where('status', 'issued')
            ->whereNotNull('ticket_number')
            ->where('ticket_number', '!=', '');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
