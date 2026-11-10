<?php

namespace App\Http\Requests;

use App\Models\Workout;
use App\Workouts\WorkoutDefinition;
use App\Workouts\WorkoutDurationInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkoutRequest extends FormRequest
{
    private array $ambiguousDurations = [];

    protected function prepareForValidation(): void
    {
        $blocks = $this->input('blocks');
        if (! is_array($blocks)) {
            return;
        }
        $duration = app(WorkoutDurationInput::class);
        foreach ($blocks as $index => &$block) {
            if (! is_array($block) || ! $duration->usesMinutes($block)) {
                continue;
            }
            if (array_key_exists('duration_seconds', $block)) {
                $this->ambiguousDurations[] = $index;
            }
            $block['duration_seconds'] = $duration->seconds($block['duration_minutes'] ?? null, $block['duration_extra_seconds'] ?? null);
        }
        unset($block);
        $this->merge(['blocks' => $blocks]);
    }

    public function workoutData(): array
    {
        return app(WorkoutDurationInput::class)->canonical($this->validated());
    }

    public function authorize(): bool
    {
        $workout = $this->route('workout');

        return $workout ? $this->user()->can('update', $workout) : $this->user()->can('create', Workout::class);
    }

    public function rules(WorkoutDefinition $definition): array
    {
        $rules = $definition->rules($this->all());
        $rules['blocks.*'] = ['array:type,title,description,notes,timer_type,timer_settings,duration_seconds,duration_minutes,duration_extra_seconds,suggested_loads,stations'];
        $rules['blocks.*.duration_minutes'] = ['nullable', 'integer', 'min:0', 'max:1440'];
        $rules['blocks.*.duration_extra_seconds'] = ['nullable', 'integer', 'min:0', 'max:59'];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->ambiguousDurations as $index) {
                $validator->errors()->add("blocks.$index.duration_minutes", 'Envie a duração em minutos ou em segundos, sem misturar as duas entradas.');
            }
            if (! $validator->errors()->has('training_modality_id')) {
                $workout = $this->route('workout');
                if ((! $workout || $this->input('status') === 'published' || (int) $this->input('training_modality_id') !== $workout->training_modality_id) && ! $this->user()->box->offersModality((int) $this->input('training_modality_id'))) {
                    $validator->errors()->add('training_modality_id', 'Modalidade desativada. Reative-a nas Configurações da Box.');
                }
            }
            app(WorkoutDefinition::class)->checkStructure($validator);
        }];
    }

    public function attributes(): array
    {
        return [...app(WorkoutDefinition::class)->attributes(), 'blocks.*.duration_minutes' => 'duração em minutos', 'blocks.*.duration_extra_seconds' => 'segundos adicionais', 'blocks.*.duration_seconds' => 'duração'];
    }

    public function messages(): array
    {
        return [
            'blocks.*.duration_seconds.min' => 'Informe pelo menos 1 segundo, usando minutos e/ou segundos adicionais.',
            'blocks.*.duration_seconds.max' => 'A duração total não pode ultrapassar 1440 minutos (24 horas).',
            'blocks.*.duration_minutes.integer' => 'Use minutos inteiros. Para tempos menores, preencha segundos adicionais.',
        ];
    }
}
