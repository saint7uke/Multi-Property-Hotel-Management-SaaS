<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_type' => ['required', 'in:personal,event'],
            'property_id' => ['required', 'exists:properties,id'],
            'room_id' => ['nullable', 'required_if:booking_type,personal', 'exists:rooms,id'],
            'event_name' => ['nullable', 'required_if:booking_type,event', 'string', 'max:160'],
            'guest_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'home_address' => ['required', 'string', 'max:255'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'preferred_area' => ['nullable', 'string', 'max:160'],
            'wants_breakfast' => ['nullable', 'boolean'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['string', 'in:extra_bed,extra_pax,additional_breakfast,early_check_in,late_check_out'],
            'payment_method' => ['required', 'string', 'in:credit_card,gcash,maya,online_banking,bank_transfer,digital_wallet'],
            'terms_accepted' => ['accepted'],
            'special_request' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
