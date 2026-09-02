<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Visa extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'fee_cents' => 'integer',
        'active' => 'boolean',
    ];
}
