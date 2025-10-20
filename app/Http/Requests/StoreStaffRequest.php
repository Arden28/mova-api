<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
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
            'name'   => ['required','string','max:255'],
            'email'  => ['nullable','email','max:255','unique:users,email'],
            'phone'  => ['nullable','string','max:50','unique:users,phone'],
            'avatar_url' => ['nullable','url'],
            'license_no' => ['nullable','string','max:100'],
            'password'   => ['nullable','string','min:8'], // can be null if you invite later
            'role'       => ['required', Rule::in(['agent','admin'])], // staff roles only
            'status'     => ['nullable', Rule::in(['active','inactive','suspended'])],
        ];
    }
}
