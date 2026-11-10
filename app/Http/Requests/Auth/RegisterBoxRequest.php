<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
    }

    public function rules(): array
    {
        return [
            'modalities' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'modalities.*' => ['required', 'integer', 'distinct', 'exists:training_modalities,id'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'primary_color' => ['sometimes', 'required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'secondary_color' => ['sometimes', 'required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=8192,max_height=8192'],
            'box_name' => ['required', 'string', 'max:120'],
            'head_coach_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/\\A[a-z0-9][a-z0-9._-]*\\z/', 'unique:users,username'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers(), function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('A senha deve ter até 72 bytes e não pode conter caracteres nulos.');
                }
            }],
        ];
    }
}
