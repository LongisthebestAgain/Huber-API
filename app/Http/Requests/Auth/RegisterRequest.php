<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:passenger,driver'],
            'location' => ['required', 'string', 'max:255'],
        ];

        if ($this->input('role') === 'driver') {
            $rules = array_merge($rules, [
                'license_number' => ['required', 'string', 'max:50'],
                'vehicle_info' => ['required', 'string', 'max:255'],
                'profile_photo' => ['required', 'image', 'max:5120'], // 5MB
            ]);
        }

        return $rules;
    }
} 