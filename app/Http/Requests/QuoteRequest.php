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
            // NEW: either provide bus IDs, or a vehicles[] array with type strings, or fallback to legacy fields.

            // Option A: by Bus IDs (we’ll look up types from DB)
            'bus_ids'        => ['sometimes','array','min:1','max:100'],
            'bus_ids.*'      => ['integer','distinct'],

            // Option B: by explicit vehicle types array (e.g., ["hiace","coaster","coaster"])
            'vehicles'       => ['sometimes','array','min:1','max:100'],
            'vehicles.*'     => [Rule::in($vehicleKeys)],

            // Legacy option (kept for backward compatibility)
            'vehicle_type'   => ['sometimes','required_without_all:bus_ids,vehicles', Rule::in($vehicleKeys)],
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
            'vehicle_type.required_without_all' => 'Provide vehicle_type+buses OR vehicles[] OR bus_ids[].',
        ];
    }
}
