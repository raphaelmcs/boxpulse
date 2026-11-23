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
        $preparationRules = $this->input('preparation_seconds') === 'custom'
            ? ['in:custom']
            : ['integer', 'min:0', 'max:60'];

        return [
            'preparation_seconds' => ['nullable', ...$preparationRules],
            'custom_preparation_seconds' => ['exclude_unless:preparation_seconds,custom', 'required', 'integer', 'min:0', 'max:60'],
            'auto_advance' => ['sometimes', 'boolean'],
            'new_session_confirmation' => ['sometimes', 'accepted'],
            'previous_session_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function sessionOptions(): array
    {
        $options = $this->validated();
        if (($options['preparation_seconds'] ?? null) === 'custom') {
            $options['preparation_seconds'] = (int) $options['custom_preparation_seconds'];
            unset($options['custom_preparation_seconds']);
        }

        return $options;
    }
}
