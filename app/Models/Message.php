<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Message extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->id ??= (string) Str::uuid();
        });
    }
}
