<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'string', 'max:255'], 'body' => ['sometimes', 'string'], 'presentation_format' => ['prohibited']];
    }
}
