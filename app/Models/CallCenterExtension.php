<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CallCenterExtension extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['can_receive_calls' => 'boolean', 'settings' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
