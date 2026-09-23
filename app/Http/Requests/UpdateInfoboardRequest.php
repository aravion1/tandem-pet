<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInfoboardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'string', 'max:255'], 'body' => ['sometimes', 'string'], 'presentation_format' => ['sometimes', Rule::in(['board', 'page', 'banner'])]];
    }
}
