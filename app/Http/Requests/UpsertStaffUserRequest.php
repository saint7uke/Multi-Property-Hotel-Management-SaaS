<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpsertStaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $role = $this->input('role');
        $status = $this->input('status');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) preg_replace('/\s+/', ' ', $this->input('name'))) : $this->input('name'),
            'email' => is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email,
            'role' => is_string($role) ? Str::of($role)->trim()->lower()->toString() : $role,
            'status' => is_string($status) ? Str::of($status)->trim()->lower()->toString() : $status,
        ]);
    }

    public function rules(): array
    {
        $staffUser = $this->route('user');
        $actor = $this->user();
        $roleChoices = $actor?->hasRole('admin')
            ? ['admin', 'manager', 'receptionist', 'housekeeping']
            : ['manager', 'receptionist', 'housekeeping'];

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($staffUser)],
            'password' => [
                $staffUser ? 'nullable' : 'required',
                'confirmed',
                Password::min(12)->mixedCase()->letters()->numbers(),
                'max:100',
            ],
            'role' => ['required', Rule::in($roleChoices)],
            'property_id' => [
                'nullable',
                'required_unless:role,admin',
                Rule::exists('properties', 'id')->where('status', 'active'),
            ],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.required_unless' => 'Choose the hotel property this staff member belongs to.',
        ];
    }
}
