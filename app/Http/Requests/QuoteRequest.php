<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $eventKeys = array_keys(config('pricing.events'));
        $vehicleKeys = array_keys(config('pricing.vehicles'));

        return [
            // Priority: bus_ids > vehicles_map > vehicles > legacy vehicle_type+buses

            // A) Bus IDs
            'bus_ids'        => ['sometimes','array','min:1','max:100'],
            'bus_ids.*'      => ['integer','distinct'],

            // B) Compact map: { "hiace": 3, "coaster": 2 }
            'vehicles_map'   => ['sometimes','array','min:1'],
            // keys must be known vehicle types; values are positive integers
            'vehicles_map.*' => ['integer','min:1'],

            // C) Flat array (fallback): ["hiace","coaster",...]
            'vehicles'       => ['sometimes','array','min:1','max:100'],
            'vehicles.*'     => [Rule::in($vehicleKeys)],

            // D) Legacy (final fallback)
            'vehicle_type'   => ['sometimes',
                                'required_without_all:bus_ids,vehicles_map,vehicles',
                                Rule::in($vehicleKeys)],
            'buses'          => ['sometimes','integer','min:1','max:100'],

            // Common
            'distance_km'    => ['required','numeric','min:0'],
            'event'          => ['nullable', Rule::in($eventKeys)],
            'when'           => ['nullable','date'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_type.required_without_all' =>
                'Provide bus_ids[] OR vehicles_map{} OR vehicles[] OR legacy vehicle_type+buses.',
        ];
    }

}
