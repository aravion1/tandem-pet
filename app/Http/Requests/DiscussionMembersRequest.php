<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscussionMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['user_ids' => ['required', 'array'], 'user_ids.*' => ['uuid', 'distinct', 'exists:users,id']];
    }
}
