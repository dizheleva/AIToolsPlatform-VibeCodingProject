<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Anyone can attempt to login
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
            'two_factor_code' => 'nullable|string|size:6',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email адресът е задължителен.',
            'email.email' => 'Email адресът трябва да е валиден.',
            'password.required' => 'Паролата е задължителна.',
            'two_factor_code.size' => '2FA кодът трябва да бъде точно 6 символа.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'email адрес',
            'password' => 'парола',
            'two_factor_code' => '2FA код',
        ];
    }
}

