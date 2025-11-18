<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owners can update user roles
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
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer,owner',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Ролята е задължителна.',
            'role.in' => 'Невалидна роля. Възможни стойности: employee, backend, frontend, qa, pm, designer, owner.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'role' => 'роля',
        ];
    }
}

