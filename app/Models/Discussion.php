<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Discussion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['is_closed' => 'boolean', 'closed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $discussion): void {
            $discussion->id ??= (string) Str::uuid();
        });
    }
}
