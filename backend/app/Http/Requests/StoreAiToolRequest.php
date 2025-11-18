<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization will be handled by Policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'url' => ['required', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'pricing_model' => ['required', Rule::in(['free', 'freemium', 'paid', 'enterprise'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'pending_review'])],
            'featured' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(['backend', 'frontend', 'qa', 'pm', 'designer'])],
            'tags' => ['nullable', 'array'],
            'documentation_url' => ['nullable', 'url', 'max:500'],
            'github_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Името на инструмента е задължително.',
            'name.max' => 'Името не може да бъде повече от 255 символа.',
            'url.required' => 'URL адресът е задължителен.',
            'url.url' => 'Моля, въведете валиден URL адрес.',
            'pricing_model.required' => 'Моделът на ценообразуване е задължителен.',
            'pricing_model.in' => 'Моделът на ценообразуване трябва да бъде: free, freemium, paid или enterprise.',
            'category_ids.*.exists' => 'Една или повече избрани категории не съществуват.',
            'roles.*.in' => 'Една или повече избрани роли са невалидни.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'име',
            'url' => 'URL адрес',
            'pricing_model' => 'модел на ценообразуване',
            'category_ids' => 'категории',
            'roles' => 'роли',
        ];
    }
}

