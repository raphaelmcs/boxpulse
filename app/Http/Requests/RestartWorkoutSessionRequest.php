<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestartWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('control', $this->route('session'));
    }

    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'client_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'], 'expected_workout_version' => ['required', 'integer', 'min:1']];
    }
}
