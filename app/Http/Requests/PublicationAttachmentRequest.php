<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicationAttachmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array { return ['file' => ['required', 'file']]; }
}
