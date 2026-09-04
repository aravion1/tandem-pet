<?php

namespace App\Http\Requests;

class LoginRequest extends PhoneRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'password' => ['required', 'string', 'max:255']];
    }
}
