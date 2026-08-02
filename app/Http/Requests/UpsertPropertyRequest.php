<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertPropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('properties.manage')) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $user->hasRole('admin');
        }

        $property = $this->route('property');

        return $user->hasRole('admin') || (int) $property?->getKey() === (int) $user->property_id;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->normalizeText($this->input('name'));
        $slugSource = $this->normalizeText($this->input('slug')) ?: $name;

        $prepared = [
            'name' => $name,
            'slug' => $this->normalizeSlug($slugSource),
            'address' => $this->normalizeText($this->input('address')),
            'city' => $this->normalizeText($this->input('city')),
            'country' => $this->normalizeText($this->input('country')),
            'offers_breakfast' => $this->has('offers_breakfast')
                ? $this->boolean('offers_breakfast')
                : ($this->route('property')?->offers_breakfast ?? true),
            'status' => is_string($this->input('status')) ? strtolower(trim($this->input('status'))) : $this->input('status'),
        ];

        foreach (['tagline', 'contact_email', 'contact_phone', 'meta_title', 'meta_description'] as $field) {
            if ($this->exists($field)) {
                $prepared[$field] = $this->normalizeText($this->input($field));
            }
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        $property = $this->route('property');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('properties', 'slug')->ignore($property),
            ],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hero_image_path' => ['nullable', 'string', 'max:255'],
            'gallery_images' => ['nullable', 'array', 'max:8'],
            'gallery_images.*' => ['string', 'max:255'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['string', 'max:100'],
            'highlights' => ['nullable', 'array', 'max:8'],
            'highlights.*.title' => ['required', 'string', 'max:100'],
            'highlights.*.description' => ['required', 'string', 'max:400'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'offers_breakfast' => ['boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and single hyphens between words.',
        ];
    }

    private function normalizeText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeSlug(mixed $value): string
    {
        return Str::slug(str_replace('&', ' and ', (string) $value));
    }
}
