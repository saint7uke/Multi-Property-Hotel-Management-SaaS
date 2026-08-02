<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rooms.manage') ?? false;
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'property_id' => ['required', 'exists:properties,id'],
            'room_number' => [
                'required',
                'string',
                'max:40',
                Rule::unique('rooms')->where('property_id', $this->input('property_id'))->ignore($room),
            ],
            'type' => ['required', 'string', 'max:120'],
            'rate' => ['required', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:80'],
            'status' => ['required', 'in:available,occupied,maintenance,dirty,cleaning,clean,inspected,ready,out_of_service'],
        ];
    }
}
