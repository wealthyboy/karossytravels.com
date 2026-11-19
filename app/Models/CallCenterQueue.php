<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CallCenterQueue extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function agents(): BelongsToMany { return $this->belongsToMany(User::class, 'call_center_queue_user')->withPivot(['priority', 'is_active']); }

    public function calls(): HasMany { return $this->hasMany(CallCenterCall::class); }
}
