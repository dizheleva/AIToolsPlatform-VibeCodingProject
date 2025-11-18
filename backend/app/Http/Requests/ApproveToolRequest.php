<?php

namespace App\Http\Requests;

use App\Models\AiTool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApproveToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owners can approve/reject tools
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
            'status' => 'required|in:active,inactive,pending_review',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Статусът е задължителен.',
            'status.in' => 'Невалиден статус. Възможни стойности: active, inactive, pending_review.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'статус',
        ];
    }
}

