<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicContactInquiryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => Str::of((string) $this->input('full_name'))->squish()->toString(),
            'email' => Str::of((string) $this->input('email'))->trim()->lower()->toString(),
            'phone' => filled($this->input('phone')) ? Str::of((string) $this->input('phone'))->squish()->toString() : null,
            'message' => Str::of((string) $this->input('message'))->trim()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('status', 'active')],
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'inquiry_type' => ['required', Rule::in(['room_reservation', 'events', 'group_booking', 'guest_services', 'partnership', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
