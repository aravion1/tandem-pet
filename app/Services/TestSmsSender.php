<?php

namespace App\Services;

use App\Contracts\SmsSender;

final class TestSmsSender implements SmsSender
{
    /** @var array<string, string> */
    private array $codes = [];

    public function send(string $phone, string $code): void
    {
        $this->codes[$phone] = $code;
    }

    public function latestCode(string $phone): ?string
    {
        return $this->codes[$phone] ?? null;
    }
}
