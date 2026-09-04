<?php

namespace App\Http\Requests;

class ActivationConfirmRequest extends PhoneRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'consent_version' => ['required', 'string', 'max:64'],
        ];
    }
}
