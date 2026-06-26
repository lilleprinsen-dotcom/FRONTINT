<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'type' => ['required', Rule::in(['woocommerce', 'front', 'dintero', 'stripe', 'webtoffee_adapter'])],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:2048'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
