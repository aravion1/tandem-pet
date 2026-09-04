<?php

namespace App\Contracts;

interface SmsSender
{
    public function send(string $phone, string $code): void;
}
