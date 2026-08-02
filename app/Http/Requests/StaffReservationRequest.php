<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'booking_type' => ['required', 'in:personal,event'],
            'property_id' => ['nullable', 'required_if:booking_type,event', 'exists:properties,id'],
            'room_id' => ['nullable', 'required_if:booking_type,personal', 'exists:rooms,id'],
            'event_name' => ['nullable', 'required_if:booking_type,event', 'string', 'max:160'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'special_request' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
        ];
    }
}
