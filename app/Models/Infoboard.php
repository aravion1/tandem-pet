<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Infoboard extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $model): void { $model->id ??= (string) Str::uuid(); });
    }
}
