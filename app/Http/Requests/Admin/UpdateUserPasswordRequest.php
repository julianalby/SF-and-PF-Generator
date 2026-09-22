<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind "auth" + "admin"
    }

    public function rules(): array
    {
        return [
            'password' => [...User::passwordRules(), 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'Password',
        ];
    }
}
