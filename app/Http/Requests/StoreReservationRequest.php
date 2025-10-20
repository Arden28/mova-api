<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code'            => ['nullable','string','max:32','unique:reservations,code'],
            'trip_date'       => ['required','date'],
            'from_location'   => ['required','string','max:255'],
            'to_location'     => ['required','string','max:255'],

            // passenger
            'passenger_name'  => ['required','string','max:120'],
            'passenger_phone' => ['required','string','max:40'],
            'passenger_email' => ['nullable','email','max:190'],

            // booking numbers
            'seats'           => ['required','integer','min:1','max:500'],
            'price_total'     => ['nullable','numeric','min:0','max:99999999.99'],

            // status
            'status'          => ['nullable', Rule::in(['pending','confirmed','cancelled'])],

            // map/route
            'waypoints'               => ['nullable','array','min:2'],
            'waypoints.*.lat'         => ['required_with:waypoints','numeric','between:-90,90'],
            'waypoints.*.lng'         => ['required_with:waypoints','numeric','between:-180,180'],
            'waypoints.*.label'       => ['nullable','string','max:255'],
            'distance_km'             => ['nullable','numeric','min:0','max:100000'],

            // buses
            'bus_ids'        => ['nullable','array'],
            'bus_ids.*'      => ['uuid','distinct','exists:buses,id'],
        ];
    }
}
