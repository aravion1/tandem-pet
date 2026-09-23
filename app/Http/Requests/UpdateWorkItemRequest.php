<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'visibility' => ['sometimes', 'nullable', Rule::in(['public', 'private'])],
            'addressee_user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'priority' => ['sometimes', 'required', 'string', 'max:32'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
