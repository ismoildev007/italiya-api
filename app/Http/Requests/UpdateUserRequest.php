<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends BaseRequest
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
        $userId = $this->route('user')?->id;

        return [
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'username'  => 'required|string|max:100|unique:users,username,' . $userId,
            'password'  => 'nullable|string|min:6',
            'roles'     => 'required|array',
            'roles.*'   => 'string|exists:roles,name',
            'projects'  => 'nullable|array',
            'projects.*'=> 'string',
        ];
    }
}
