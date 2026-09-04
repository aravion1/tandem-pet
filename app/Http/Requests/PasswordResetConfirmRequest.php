<?php

namespace App\Http\Requests;

class PasswordResetConfirmRequest extends PhoneRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ];
    }
}
