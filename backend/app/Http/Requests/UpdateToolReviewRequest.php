<?php

namespace App\Http\Requests;

use App\Models\ToolReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateToolReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('review');
        
        // Check if user owns this review
        return Auth::check() && $review->user_id === Auth::id();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => 'sometimes|required|integer|min:1|max:5',
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
}

