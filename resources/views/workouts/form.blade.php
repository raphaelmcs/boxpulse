@extends('layouts.app')
@section('title', $workout ? 'Editar treino' : 'Novo treino')
@section('content')
<x-workouts.navigation />
@if($workout && ! $box->offersModality($workout->training_modality_id))<p class="notice">Modalidade desativada. O treino está preservado para consulta. Reative a modalidade em Configurações para publicar, copiar ou preparar novas aulas. Edições podem ser salvas como rascunho.</p>@endif
@php
    $values = app(\App\Workouts\WorkoutDefinition::class)->forEditor($form, session()->getOldInput());
    $structure = $values['structure'] ?? 'main';
    $types = $structure === 'full' ? ['warmup', 'skill', 'workout'] : ['workout'];
    $byType = collect($values['blocks'] ?? [])->keyBy('type');
@endphp
<div class="page-heading"><p class="eyebrow">{{ $modality->name }} / {{ $structure === 'full' ? 'AULA COMPLETA' : 'TREINO PRINCIPAL' }}</p><h1>{{ $workout ? 'Ajuste o treino' : 'Prepare o treino' }}<span>.</span></h1><p class="hero-copy">Campos com * são obrigatórios para publicar. Rascunhos podem ficar incompletos.</p></div>
<form method="POST" action="{{ $workout ? route('workouts.update', $workout) : route('workouts.store') }}" class="workout-form" data-workout-form>
    @csrf @if($workout) @method('PUT') @endif
    <input type="hidden" name="training_modality_id" value="{{ $modality->id }}"><input type="hidden" name="structure" value="{{ $structure }}">
    <section class="form-panel"><div class="field-grid two-columns"><label>Data da aula *<input type="date" name="scheduled_for" value="{{ $values['scheduled_for'] }}" required min="2000-01-01" max="2100-12-31"></label><label>Nome do treino (opcional)<input name="title" value="{{ $values['title'] ?? '' }}" maxlength="160" placeholder="Ex.: Engine Day"></label></div><p class="input-hint">Fuso horário: {{ $box->timezone }}. Você pode cadastrar vários treinos na mesma data.</p></section>
    @if($structure === 'full')<fieldset class="form-panel optional-stages"><legend>Etapas da aula</legend><p class="input-hint">Escolha uma ou mais etapas. Somente etapas preenchidas serão salvas, na ordem abaixo.</p><div class="button-row">@foreach(['warmup' => 'Aquecimento', 'skill' => 'Técnica / Skill', 'workout' => 'WOD / Principal'] as $key => $label)<label class="checkbox-label"><input type="checkbox" data-block-toggle="{{ $key }}" @checked($byType->has($key) || (! $workout && ! session()->hasOldInput() && $key === 'workout'))>{{ $label }}</label>@endforeach</div></fieldset>@endif
    @foreach($types as $index => $type)
        @php($block = $byType->get($type, ['type' => $type, 'timer_type' => $type === 'workout' ? ($modality->slug === 'functional' ? 'circuit' : 'for_time') : 'none']))
        @php($enabled = $structure !== 'full' || $byType->has($type) || (! $workout && ! session()->hasOldInput() && $type === 'workout'))
        <fieldset class="stage-fields" data-stage="{{ $type }}" @disabled(! $enabled) @if(! $enabled) hidden @endif><x-workouts.block-fields :index="$index" :block="$block" :modality="$modality" /></fieldset>
    @endforeach
    <script type="application/json" data-timer-registry>@json(\App\Workouts\TimerConfiguration::registry())</script>
    <datalist id="skill-suggestions">@foreach(['Snatch', 'Clean', 'Clean & Jerk', 'Deadlift', 'Squat', 'Pull-up', 'Muscle-up', 'HSPU', 'Handstand Walk', 'Toes to Bar'] as $skill)<option value="{{ $skill }}">@endforeach</datalist>
    <datalist id="station-suggestions">@foreach(['SkiErg', 'Sled Push', 'Sled Pull', 'Burpee Broad Jumps', 'Row', 'Farmers Carry', 'Sandbag Lunges', 'Wall Balls', 'Running'] as $exercise)<option value="{{ $exercise }}">@endforeach</datalist>
    <x-workouts.presentation-check />
    <div class="form-actions"><label>Status<select name="status"><option value="draft" @selected(($box->offersModality($modality->id) ? ($values['status'] ?? 'draft') : 'draft') === 'draft')>Rascunho</option>@if($box->offersModality($modality->id))<option value="published" @selected(($box->offersModality($modality->id) ? ($values['status'] ?? 'draft') : 'draft') === 'published')>Publicado</option>@endif</select></label><button type="submit" class="secondary-button" name="preview_after_save" value="1">Salvar e pré-visualizar</button><button type="submit" class="primary-button compact">Salvar treino ↗</button><span data-save-feedback role="status"></span></div>
</form>
@endsection
