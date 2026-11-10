<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrepareClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workout'));
    }

    public function rules(): array
    {
        return [
            'preparation_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'auto_advance' => ['sometimes', 'boolean'],
            'new_session_confirmation' => ['sometimes', 'accepted'],
            'previous_session_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
