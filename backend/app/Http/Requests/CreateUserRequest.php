<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owners can create users
        return Auth::check() && 
               Auth::user()->role === 'owner' && 
               Auth::user()->status === 'approved';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer,owner',
            'status' => 'nullable|in:pending,approved,rejected',
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
            'role.required' => 'Ролята е задължителна.',
            'role.in' => 'Невалидна роля.',
            'status.in' => 'Невалиден статус.',
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
            'status' => 'статус',
        ];
    }
}

