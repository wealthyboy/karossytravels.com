<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PartnerEnquiry extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['email_delivery' => 'array'];
    }
}
