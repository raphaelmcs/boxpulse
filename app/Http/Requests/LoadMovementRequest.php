<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $movement = $this->route('movement');

        return $this->user()->role === 'admin' && (! $movement || (int) $movement->box_id === (int) $this->user()->box_id);
    }

    public function rules(): array
    {
        $label = fn (int $limit) => ['string', 'max:'.$limit, 'regex:/\A[^\x00-\x1F\x7F]+\z/u'];

        return [
            'box_id' => ['prohibited'],
            'name' => ['required', ...$label(120)],
            'equipment' => ['required', ...$label(80)],
            'variation' => ['nullable', ...$label(80)],
            'modalities' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'modalities.*' => ['required', 'distinct', Rule::exists('training_modalities', 'slug')],
            'aliases' => ['present', 'array', 'list', 'max:16'],
            'aliases.*' => ['required', ...$label(100)],
            'active' => ['required', 'boolean'],
        ];
    }
}
