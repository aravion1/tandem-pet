<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportResidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'residents' => ['required', 'array', 'min:1', 'max:1000'],
            'residents.*.phone' => ['required', 'string', 'max:32'],
            'residents.*.street' => ['required', 'string', 'max:255'],
            'residents.*.house' => ['required', 'string', 'max:64'],
        ];
    }
}
