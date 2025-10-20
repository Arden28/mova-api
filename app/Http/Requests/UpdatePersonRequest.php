<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
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
        $id = $this->route('person'); // using {person} binding

        return [
            'name'   => ['sometimes','required','string','max:255'],
            'email'  => ['sometimes','nullable','email','max:255', Rule::unique('users','email')->ignore($id)],
            'phone'  => ['sometimes','nullable','string','max:50', Rule::unique('users','phone')->ignore($id)],
            'avatar_url' => ['sometimes','nullable','url'],
            'license_no' => ['sometimes','nullable','string','max:100'],
            'password'   => ['sometimes','nullable','string','min:8'],
            'role'       => ['sometimes','required', Rule::in(['driver','conductor', 'owner'])],
            'status'     => ['sometimes','required', Rule::in(['active','inactive','suspended'])],
        ];
    }
}
