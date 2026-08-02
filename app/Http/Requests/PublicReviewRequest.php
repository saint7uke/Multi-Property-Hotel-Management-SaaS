<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $referenceNumber = $this->input('reference_number');
        $email = $this->input('email');

        $this->merge([
            'reference_number' => is_string($referenceNumber)
                ? Str::of($referenceNumber)->replaceMatches('/\s+/', '')->upper()->toString() ?: null
                : $referenceNumber,
            'email' => is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => [
                'nullable',
                'required_without:reference_number',
                Rule::exists('properties', 'id')->where('status', 'active'),
            ],
            'reference_number' => ['nullable', 'string', 'max:40'],
            'guest_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'stay_type' => ['nullable', Rule::in(['personal', 'event_group', 'guest_inquiry'])],
            'message' => ['required', 'string', 'min:10', 'max:1200'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.required_without' => 'Choose a property or enter a booking reference number.',
        ];
    }
}
