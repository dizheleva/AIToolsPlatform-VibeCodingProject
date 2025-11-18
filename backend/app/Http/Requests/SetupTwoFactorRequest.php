<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupTwoFactorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:email,telegram,google_authenticator',
            'telegram_chat_id' => 'required_if:type,telegram|nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Типът на 2FA е задължителен.',
            'type.in' => 'Невалиден тип 2FA. Валидни типове: email, telegram, google_authenticator.',
            'telegram_chat_id.required_if' => 'Telegram Chat ID е задължителен, когато типът е telegram.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'тип',
            'telegram_chat_id' => 'telegram chat id',
        ];
    }
}

