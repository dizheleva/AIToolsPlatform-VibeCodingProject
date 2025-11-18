<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Category::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Името на категорията е задължително.',
            'name.max' => 'Името на категорията не може да надвишава :max символа.',
            'color.regex' => 'Цветът трябва да е в HEX формат (#RRGGBB).',
            'parent_id.exists' => 'Избраната родителска категория не съществува.',
            'order.min' => 'Редът трябва да е положително число.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'име',
            'description' => 'описание',
            'icon' => 'икона',
            'color' => 'цвят',
            'parent_id' => 'родителска категория',
            'order' => 'ред',
            'is_active' => 'активна',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Only owners can create categories.',
        ], 403));
    }
}

