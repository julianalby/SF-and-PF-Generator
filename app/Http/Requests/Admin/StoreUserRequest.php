<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind "auth" + "admin"
    }

    public function rules(): array
    {
        return [
            'username' => User::usernameRules(),
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => [...User::passwordRules(), 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'Username',
            'role' => 'Role',
            'password' => 'Password',
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The Username may only contain letters, numbers, dots, underscores and hyphens.',
        ];
    }
}
