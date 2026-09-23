<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInfoboardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string'], 'presentation_format' => ['required', Rule::in(['board', 'page', 'banner'])]];
    }
}
