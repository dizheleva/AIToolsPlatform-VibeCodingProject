<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreToolReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user is authenticated and approved
        // The check for existing review will be done in the controller
        return Auth::check() && Auth::user()->status === 'approved';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Рейтингът е задължителен.',
            'rating.integer' => 'Рейтингът трябва да бъде число.',
            'rating.min' => 'Рейтингът трябва да бъде поне 1.',
            'rating.max' => 'Рейтингът не може да бъде повече от 5.',
            'comment.max' => 'Коментарът не може да бъде повече от 2000 символа.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'rating' => 'рейтинг',
            'comment' => 'коментар',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Your account must be approved to write reviews.',
            ], 403)
        );
    }
}

