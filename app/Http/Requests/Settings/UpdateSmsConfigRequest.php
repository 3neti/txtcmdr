<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'api_key' => 'nullable|string|max:255',
            'org_id' => 'nullable|string|max:255',
            'default_sender_id' => 'required|string|max:255',
            'sender_ids' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_key.required' => 'The EngageSPARK API Key is required.',
            'org_id.required' => 'The EngageSPARK Organization ID is required.',
            'default_sender_id.required' => 'The default sender ID is required.',
        ];
    }
}
