<?php

namespace App\Http\Requests;

use App\Models\Workout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Workout::class);
    }

    public function rules(): array
    {
        return ['source_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-24'], 'target_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-24'], 'conflict_policy' => ['required', Rule::in(['abort', 'append'])], 'confirmation' => ['accepted']];
    }
}
