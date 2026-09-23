<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $workItem): void {
            $workItem->id ??= (string) Str::uuid();
        });
    }
}
