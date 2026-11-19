<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CallCenterCall extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'started_at' => 'datetime', 'answered_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function agent(): BelongsTo { return $this->belongsTo(User::class, 'agent_user_id'); }
    public function queue(): BelongsTo { return $this->belongsTo(CallCenterQueue::class, 'call_center_queue_id'); }
}
