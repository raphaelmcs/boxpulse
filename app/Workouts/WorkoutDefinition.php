<?php

namespace App\Workouts;

use App\Models\TrainingModality;
use App\Models\Workout;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;

class WorkoutDefinition
{
    public function rules(array $data): array
    {
        $published = ($data['status'] ?? null) === 'published';
        $rules = [
            'training_modality_id' => ['required', 'integer', 'exists:training_modalities,id'],
            'structure' => ['required', Rule::in(['full', 'main'])],
            'title' => ['nullable', 'string', 'max:160'],
            'scheduled_for' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'blocks' => [$published ? 'required' : 'sometimes', 'array', 'list', 'max:3'],
            'blocks.*' => ['array:type,title,description,notes,timer_type,timer_settings,duration_seconds,suggested_loads,stations'],
            'blocks.*.type' => ['required', Rule::in(['warmup', 'skill', 'workout']), 'distinct:strict'],
            'blocks.*.title' => ['nullable', 'string', 'max:160'],
            'blocks.*.description' => ['nullable', 'string', 'max:20000'],
            'blocks.*.suggested_loads' => ['nullable', 'array:equipment,rx,intermediate,scale,unit'],
            'blocks.*.suggested_loads.equipment' => ['nullable', 'string', 'max:80', 'regex:/\A[^\x00-\x1F\x7F]*\z/u'],
            'blocks.*.suggested_loads.unit' => ['nullable', Rule::in(['kg', 'lb'])],
            ...collect(SuggestedLoads::LEVELS)->mapWithKeys(fn ($label, $key) => ["blocks.*.suggested_loads.$key" => ['nullable', 'string', 'max:60', 'regex:/\A[^\x00-\x1F\x7F]*\z/u']])->all(),
            'blocks.*.notes' => ['nullable', 'string', 'max:5000'],
            'blocks.*.timer_type' => ['required', Rule::in(array_keys(TimerConfiguration::LABELS))],
            'blocks.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'blocks.*.timer_settings' => ['sometimes', 'array:preparation_seconds,interval_seconds,work_seconds,rest_seconds,rounds,transition_seconds,stop_at_cap'],
            ...collect(TimerConfiguration::parameterRules())->mapWithKeys(fn ($rules, $field) => ["blocks.*.timer_settings.$field" => $rules])->all(),
            'blocks.*.stations' => ['sometimes', 'array', 'list', 'max:64'],
            'blocks.*.stations.*' => ['array:name,description,distance,repetitions,load,duration_seconds,rest_seconds,transition_seconds'],
            'blocks.*.stations.*.name' => [$published ? 'required' : 'nullable', 'string', 'max:160'],
            'blocks.*.stations.*.description' => ['nullable', 'string', 'max:5000'],
            'blocks.*.stations.*.distance' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'blocks.*.stations.*.repetitions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'blocks.*.stations.*.load' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'blocks.*.stations.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'blocks.*.stations.*.rest_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'blocks.*.stations.*.transition_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ];

        return $rules;
    }

    public function checkStructure(LaravelValidator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $data = $validator->getData();
        $blocks = $this->meaningfulBlocks($data['blocks'] ?? []);
        $published = $data['status'] === 'published';
        $modality = TrainingModality::findOrFail($data['training_modality_id'])->slug;
        $types = array_column($blocks, 'type');
        $expected = $data['structure'] === 'full' ? ['warmup', 'skill', 'workout'] : ['workout'];
        if (($published && $types === []) || array_diff($types, $expected) !== [] || ($types !== [] && $types !== array_values(array_intersect($expected, $types)))) {
            $validator->errors()->add('blocks', 'Preencha ao menos uma etapa para publicar, na ordem aquecimento, técnica e principal. No modo simplificado, use apenas o principal.');
        }
        foreach ($blocks as $index => $block) {
            $key = "blocks.$index";
            if ($block['type'] !== 'workout' && SuggestedLoads::normalize($block['suggested_loads'] ?? null)) {
                $validator->errors()->add("$key.suggested_loads", 'Nesta fase, cadastre cargas somente no bloco principal.');
            }
            $type = $block['timer_type'];
            if (! array_key_exists($type, TimerConfiguration::formats($modality, $block['type']))) {
                $validator->errors()->add("$key.timer_type", 'Formato indisponível para esta modalidade e bloco.');
            }
            if (in_array($type, ['none', 'repetitions', 'tabata', 'interval', 'circuit'], true) && ! empty($block['duration_seconds'])) {
                $validator->errors()->add("$key.duration_seconds", 'Neste formato, o tempo é livre ou calculado pelos intervalos.');
            }
            if (! $published) {
                continue;
            }
            if (TimerConfiguration::registry()[$type]['duration_required'] && empty($block['duration_seconds'])) {
                $validator->errors()->add("$key.duration_seconds", 'Informe a duração para publicar.');
            }
            foreach (TimerConfiguration::settingKeys($type) as $setting) {
                if (! in_array($setting, ['transition_seconds', 'stop_at_cap'], true) && ! isset($block['timer_settings'][$setting]) && ! array_key_exists($setting, TimerConfiguration::defaults())) {
                    $validator->errors()->add("$key.timer_settings.$setting", 'Preencha este parâmetro para publicar.');
                }
            }
            if ($type === 'emom' && isset($block['duration_seconds']) && ($block['duration_seconds'] % ($block['timer_settings']['interval_seconds'] ?? TimerConfiguration::defaults()['interval_seconds']) !== 0)) {
                $validator->errors()->add("$key.timer_settings.interval_seconds", 'A duração total deve ser um múltiplo do intervalo.');
            }
            if ($type === 'circuit' && empty($block['stations'])) {
                $validator->errors()->add("$key.stations", 'Adicione pelo menos uma estação ao circuito.');
            }
        }
    }

    public function meaningfulBlocks(array $blocks): array
    {
        return array_filter($blocks, function (array $block): bool {
            foreach (['title', 'description', 'notes'] as $field) {
                if (trim($block[$field] ?? '') !== '') {
                    return true;
                }
            }

            return ! empty($block['stations']) || SuggestedLoads::normalize($block['suggested_loads'] ?? null) !== null;
        });
    }

    public function validate(array $data): array
    {
        $validator = Validator::make($data, $this->rules($data), [], $this->attributes());
        $validator->after(fn (LaravelValidator $validator) => $this->checkStructure($validator));

        $validated = $validator->validate();
        $validated['blocks'] = array_values($this->meaningfulBlocks($validated['blocks'] ?? []));

        return $validated;
    }

    public function fromWorkout(Workout $workout): array
    {
        return [
            'training_modality_id' => $workout->training_modality_id,
            'title' => $workout->title, 'scheduled_for' => $workout->scheduled_for->format('Y-m-d'),
            'structure' => $workout->structure, 'status' => $workout->status,
            'blocks' => $workout->blocks->map(fn ($block) => [
                'type' => $block->type, 'title' => $block->title, 'description' => $block->description, 'notes' => $block->notes,
                'suggested_loads' => $block->suggested_loads,
                'timer_type' => $block->timer_type, 'duration_seconds' => $block->duration_seconds,
                'timer_settings' => array_diff_key($block->timer_settings ?? [], ['version' => true]),
                'stations' => $block->stations->map(fn ($station) => $station->only(['name', 'description', 'distance', 'repetitions', 'load', 'duration_seconds', 'rest_seconds', 'transition_seconds']))->all(),
            ])->all(),
        ];
    }

    public function attributes(): array
    {
        return ['blocks.*.suggested_loads.equipment' => 'equipamento ou referência', 'blocks.*.suggested_loads.rx' => 'carga RX', 'blocks.*.suggested_loads.intermediate' => 'carga Intermediate', 'blocks.*.suggested_loads.scale' => 'carga Scale', 'blocks.*.suggested_loads.unit' => 'unidade', 'training_modality_id' => 'modalidade', 'scheduled_for' => 'data', 'structure' => 'estrutura', 'status' => 'status', 'blocks.*.description' => 'descrição', 'blocks.*.stations.*.name' => 'nome da estação'];
    }

    public function forEditor(array $base, array $old): array
    {
        $values = array_replace($base, $old);
        foreach (['title', 'scheduled_for'] as $field) {
            if (! is_scalar($values[$field] ?? null)) {
                $values[$field] = $base[$field] ?? '';
            }
        }
        foreach (['structure' => ['full', 'main'], 'status' => ['draft', 'published']] as $field => $allowed) {
            if (! in_array($values[$field] ?? null, $allowed, true)) {
                $values[$field] = $base[$field] ?? $allowed[0];
            }
        }
        $scalarFields = fn (array $fields): array => array_map(fn ($value) => is_scalar($value) ? $value : '', $fields);
        $blocks = is_array($values['blocks'] ?? null) ? $values['blocks'] : [];
        $values['blocks'] = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, ['warmup', 'skill', 'workout'], true)) {
                continue;
            }
            $settings = is_array($block['timer_settings'] ?? null) ? $scalarFields($block['timer_settings']) : [];
            $stations = is_array($block['stations'] ?? null) ? array_values(array_filter($block['stations'], 'is_array')) : [];
            $values['blocks'][] = [...$scalarFields($block), 'suggested_loads' => is_array($block['suggested_loads'] ?? null) ? $scalarFields($block['suggested_loads']) : [], 'timer_settings' => $settings, 'stations' => array_map($scalarFields, $stations)];
        }

        return $values;
    }
}
