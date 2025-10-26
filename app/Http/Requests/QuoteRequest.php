<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
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
            'vehicle_type' => ['required','in:hiace,coaster'],
            'distance_km'  => ['required','numeric','min:0'],
            'event'   => ['nullable','in:none,wedding,funeral,church'],
            'buses'        => ['nullable','integer','min:1','max:100'],
            // 'when'       => ['nullable','date'] // reserved for future time-based rules
        ];
    }
}
