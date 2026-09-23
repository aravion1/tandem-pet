<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['request', 'task'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'addressee_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'priority' => ['required', 'string', 'max:32'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
