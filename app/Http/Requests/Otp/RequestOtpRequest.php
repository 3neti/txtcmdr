<?php

namespace App\Http\Requests\Otp;

use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:32'],
            'purpose' => ['sometimes', 'string', 'max:50'],
            'external_ref' => ['sometimes', 'string', 'max:100'],
            'meta' => ['sometimes', 'array'],
        ];
    }
}
