<?php

namespace App\Http\Requests;

use App\Workouts\SuggestedLoads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $preset = $this->route('preset');

        return $this->user()->box_id !== null && $this->user()->role === 'admin'
            && (! $preset || (int) $preset->box_id === (int) $this->user()->box_id);
    }

    public function rules(): array
    {
        $rules = [
            'box_id' => ['prohibited'],
            'equipment' => ['required', 'string', 'max:80', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'movement' => ['nullable', 'string', 'max:120', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'movement_id' => ['nullable', 'integer', Rule::exists('load_movements', 'id')->where('box_id', $this->user()->box_id)],
            'name' => ['nullable', 'string', 'max:120', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'context' => ['nullable', 'in:technique,strength,conditioning'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'reference' => ['nullable', 'string', 'max:160', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'unit' => ['required', 'in:kg'],
            'active' => ['required', 'boolean'],
            'levels' => ['present', 'array:rx,intermediate,scale'],
        ];
        foreach (SuggestedLoads::LEVELS as $level => $label) {
            $rules["levels.$level"] = ['sometimes', 'array:male,female'];
            foreach (['male', 'female'] as $group) {
                $rules["levels.$level.$group"] = ['nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,3'];
            }
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $levels = $this->input('levels');
        if (! is_array($levels)) {
            return;
        }

        foreach ($levels as $level => $weights) {
            if (! is_array($weights)) {
                continue;
            }
            foreach ($weights as $group => $weight) {
                if (is_string($weight) && preg_match('/\A[0-9]+,[0-9]{1,3}\z/', $weight)) {
                    $levels[$level][$group] = str_replace(',', '.', $weight);
                }
            }
        }
        $this->merge(['levels' => $levels]);
    }
}
