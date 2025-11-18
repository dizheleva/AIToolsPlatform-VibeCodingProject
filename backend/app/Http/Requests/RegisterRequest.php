<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Anyone can register
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Името е задължително.',
            'name.max' => 'Името не може да надвишава :max символа.',
            'email.required' => 'Email адресът е задължителен.',
            'email.email' => 'Email адресът трябва да е валиден.',
            'email.unique' => 'Този email адрес вече е регистриран.',
            'password.required' => 'Паролата е задължителна.',
            'password.min' => 'Паролата трябва да бъде поне :min символа.',
            'password.confirmed' => 'Паролите не съвпадат.',
            'role.required' => 'Ролята е задължителна.',
            'role.in' => 'Невалидна роля.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'име',
            'email' => 'email адрес',
            'password' => 'парола',
            'role' => 'роля',
        ];
    }
}

