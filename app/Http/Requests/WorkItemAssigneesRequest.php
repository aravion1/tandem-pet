<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkItemAssigneesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignees' => ['required', 'array'],
            'assignees.*.user_id' => ['nullable', 'uuid', 'exists:users,id', 'required_without:assignees.*.external_name'],
            'assignees.*.external_name' => ['nullable', 'string', 'max:255', 'required_without:assignees.*.user_id'],
        ];
    }
}
