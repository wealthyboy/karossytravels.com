<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ServiceEnquiry extends Model
{
    use HasUuids;

    protected $fillable = ['reference', 'type', 'status', 'name', 'email', 'phone', 'details', 'email_delivery'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'email_delivery' => 'array'];
    }
}
