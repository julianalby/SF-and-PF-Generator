<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is behind the "auth" middleware
    }

    public function rules(): array
    {
        // Only Project Name is required. `sf_number` is an optional, free-text
        // REFERENCE to an SF; the PF number itself is never accepted from the client.
        return [
            'project_name' => ['required', 'string', 'max:255'],
            'sf_number' => ['nullable', 'string', 'max:100'],
            'vnid' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_name' => 'Project Name',
            'sf_number' => 'SF Number',
            'vnid' => 'VNID',
            'customer_name' => 'Customer Name',
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
