<?php

namespace App\Http\Requests;

use App\Models\Workout;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Workout::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:warmup,skill,workout'],
            'modality' => ['nullable', 'in:crossfit,hyrox,functional'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'stations' => ['sometimes', 'array', 'list', 'max:64'],
            'stations.*' => ['array:name,description,load'],
            'stations.*.name' => ['nullable', 'string', 'max:160'],
            'stations.*.description' => ['nullable', 'string', 'max:5000'],
            'stations.*.load' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
