<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SessionCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('control', $this->route('session'));
    }

    public function rules(): array
    {
        return ['client_id' => ['required', 'uuid'], 'command_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['start', 'pause', 'resume', 'reset', 'complete_block', 'next', 'previous', 'finish', 'cancel'])], 'confirmation' => ($this->input('action') === 'reset' ? ['required', 'accepted'] : ['sometimes', 'accepted'])];
    }
}
