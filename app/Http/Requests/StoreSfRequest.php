<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is behind the "auth" middleware
    }

    public function rules(): array
    {
        // Deliberately no rule for sf_number / user: they are never accepted from the client.
        return [
            'vnid' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'service' => ['required', 'string', 'max:255'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vnid' => 'VNID',
            'customer_name' => 'Customer Name',
            'service' => 'Service',
        ];
    }

    public function messages(): array
    {
        return [
            'submission_token.required' => 'This form is no longer valid. Please reload the page and try again.',
            'submission_token.uuid' => 'This form is no longer valid. Please reload the page and try again.',
        ];
    }
}
