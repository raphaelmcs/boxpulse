<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()->box_id !== null;
    }

    public function rules(): array
    {
        return [
            'modalities' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'modalities.*' => ['required', 'integer', 'distinct', 'exists:training_modalities,id'],
            'name' => ['required', 'string', 'max:120'],
            'head_coach_name' => ['required', 'string', 'max:120'],
            'administrator_name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'primary_color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'secondary_color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'remove_logo' => ['sometimes', 'boolean'],
            'logo' => ['nullable', Rule::prohibitedIf($this->boolean('remove_logo')), 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=8192,max_height=8192'],
        ];
    }
}
