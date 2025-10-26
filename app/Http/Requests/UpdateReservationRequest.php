<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
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
        $id = $this->route('reservation'); // {reservation} is UUID

        return [
            'code'            => ['sometimes','nullable','string','max:32', Rule::unique('reservations','code')->ignore($id)],
            'trip_date'       => ['sometimes','required','date'],
            'from_location'   => ['sometimes','required','string','max:255'],
            'to_location'     => ['sometimes','required','string','max:255'],

            'passenger_name'  => ['sometimes','required','string','max:120'],
            'passenger_phone' => ['sometimes','required','string','max:40'],
            'passenger_email' => ['sometimes','nullable','email','max:190'],

            'seats'           => ['sometimes','required','integer','min:1','max:500'],
            'price_total'     => ['sometimes','nullable','numeric','min:0','max:99999999.99'],

            'status'          => ['sometimes','required', Rule::in(['pending','confirmed','cancelled'])],

            'waypoints'               => ['sometimes','nullable','array','min:2'],
            // 'waypoints.*.lat'         => ['required_with:waypoints','numeric','between:-90,90'],
            // 'waypoints.*.lng'         => ['required_with:waypoints','numeric','between:-180,180'],
            // 'waypoints.*.label'       => ['nullable','string','max:255'],
            'distance_km'             => ['sometimes','nullable','numeric','min:0','max:100000'],

            // when present, we’ll sync() with these
            'bus_ids'        => ['sometimes','nullable','array'],
            'bus_ids.*'      => ['uuid','distinct','exists:buses,id'],

            'event'    => ['sometimes','required', Rule::in(['none','wedding','funeral','church'])],
        ];
    }
}
