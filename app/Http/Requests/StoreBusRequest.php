<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusRequest extends FormRequest
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
            'plate'   => ['required','string','max:50','unique:buses,plate'],
            'capacity'=> ['required','integer','min:1','max:200'],
            'name'    => ['nullable','string','max:100'],
            'type'    => ['nullable', Rule::in(['hiace','coaster'])],
            'status'  => ['nullable', Rule::in(['active','maintenance','inactive'])],
            'model'   => ['nullable','string','max:100'],
            'year'    => ['nullable','integer','min:1970'],
            'mileage_km' => ['nullable','integer','min:0'],
            'last_service_date' => ['nullable','date'],

            'insurance_provider'       => ['nullable','string','max:150'],
            'insurance_policy_number'  => ['nullable','string','max:100'],
            'insurance_valid_until'    => ['nullable','date','after_or_equal:last_service_date'],

            'operator_id' => [
                'nullable',
                Rule::exists('users','id')->where(fn($q)=>$q->whereIn('role',['owner','admin'])),
            ],
            'assigned_driver_id' => [
                'nullable',
                Rule::exists('users','id')->where(fn($q)=>$q->where('role','driver')),
            ],
        ];
    }
}
