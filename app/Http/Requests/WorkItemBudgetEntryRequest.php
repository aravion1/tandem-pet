<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkItemBudgetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'recorded_at' => ['required', 'date'],
        ];
    }
}
