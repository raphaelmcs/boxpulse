@extends('layouts.app')
@section('title', $workout ? 'Editar treino' : 'Novo treino')
@section('content')

@if($workout && ! $box->offersModality($workout->training_modality_id))<p class="notice">Modalidade desativada. O treino está preservado para consulta. Reative a modalidade em Configurações para publicar, copiar ou preparar novas aulas. Edições podem ser salvas como rascunho.</p>@endif
@php
    $values = app(\App\Workouts\WorkoutDefinition::class)->forEditor($form, session()->getOldInput());
    $structure = $values['structure'] ?? 'full';
    $types = ['warmup' => 'Aquecimento / Warm-up', 'skill' => 'Skill / Técnica', 'workout' => 'WOD'];
    $newWorkout = ! $workout && ! session()->hasOldInput();
    $byType = collect($values['blocks'] ?? [])->keyBy('type');
@endphp
<x-workouts.navigation :schedule-date="$workout?->scheduled_for->toDateString() ?? $form['scheduled_for']" />
<div class="page-heading"><p class="eyebrow">{{ $modality->name }} / <span data-workout-structure-label>{{ $structure === 'full' ? 'AULA COMPLETA' : 'TREINO PRINCIPAL' }}</span></p><h1>{{ $workout ? 'Ajuste o treino' : 'Prepare o treino' }}<span>.</span></h1><p class="hero-copy">Campos com * são obrigatórios para publicar. Rascunhos podem ficar incompletos.</p></div>
<form method="POST" action="{{ $workout ? route('workouts.update', $workout) : route('workouts.store') }}" id="workout-editor" class="workout-form" data-workout-form
    data-editor-version="{{ $workout?->edit_version ?? 0 }}" data-workout-modality="{{ $modality->slug }}"
    data-is-published="{{ $workout?->status === 'published' ? '1' : '0' }}"
    data-library-url="{{ route('loads.index') }}" data-library-store-url="{{ route('loads.store') }}"
    data-analysis-url="{{ route('loads.analyze') }}"
    @if($workout) data-editor-edit-url="{{ route('workouts.edit', $workout) }}" data-editor-preview-url="{{ route('workouts.preview', ['workout' => $workout, 'draft' => $workout->hasUnpublishedChanges() ? 1 : null]) }}" @endif>
    @csrf @if($workout) @method('PUT') @endif
    <input type="hidden" name="expected_edit_version" value="{{ $workout?->edit_version ?? 0 }}">
    <input type="hidden" name="editor_write_id" value="{{ \Illuminate\Support\Str::uuid() }}">
    <div class="editor-save-panel form-panel">
        <div><strong data-save-publication>{{ $workout?->status === 'published' ? ($workout->hasUnpublishedChanges() ? 'Publicado · rascunho de alterações' : 'Publicado') : 'Rascunho' }}</strong>
        <p data-save-feedback role="status" aria-live="polite"></p>
        <button type="button" class="text-button" data-save-retry hidden>Tentar novamente</button>
        <button type="button" class="text-button" data-save-backup hidden>Baixar minhas alterações</button>
        <a class="text-link" data-save-latest target="_blank" rel="noopener" hidden>Abrir revisão atual</a></div>
        <div class="button-row">
            <button type="submit" class="primary-button compact" name="editor_intent" value="draft">Salvar</button>
            <button type="submit" class="secondary-button" name="editor_intent" value="preview">Salvar e pré-visualizar</button>
            @if($box->offersModality($modality->id))<button type="submit" class="secondary-button" name="editor_intent" value="publish" data-editor-publish>{{ $workout?->status === 'published' ? 'Republicar treino' : 'Publicar treino' }}</button>@endif
            <button type="button" class="secondary-button compact" data-open-tv-preview aria-haspopup="dialog" aria-controls="tv-preview">Visualizar na TV</button>
            <a class="text-link" data-editor-preview target="_blank" rel="noopener" hidden>Ver pré-visualização ↗</a>
        </div>
    </div>
    <input type="hidden" name="training_modality_id" value="{{ $modality->id }}"><input type="hidden" name="structure" value="{{ $structure }}" data-workout-structure>
    <section class="form-panel"><div class="field-grid two-columns"><label>Data da aula *<input type="date" name="scheduled_for" value="{{ $values['scheduled_for'] }}" required min="2000-01-01" max="2100-12-31"></label><label>Nome do treino (opcional)<input name="title" value="{{ $values['title'] ?? '' }}" maxlength="160" placeholder="Ex.: Engine Day"></label></div><p class="input-hint">Fuso horário: {{ $box->timezone }}. Você pode cadastrar vários treinos na mesma data.</p></section>
    <div data-full-workout-switch @if($structure === 'full') hidden @endif>
        <button type="button" class="secondary-button" data-enable-full-workout>+ Adicionar outras etapas</button>
    </div>
    <fieldset class="form-panel optional-stages" data-workout-stage-controls @if($structure === 'main') hidden @endif>
        <legend>Etapas da aula</legend>
        <p class="input-hint">Escolha as etapas na ordem da aula. Só as etapas com conteúdo aparecem na apresentação.</p>
        <div class="button-row">
            @foreach($types as $type => $label)
            <label class="stage-toggle">
                <input type="checkbox" data-block-toggle="{{ $type }}" @checked($byType->has($type) || ($newWorkout && $type === 'workout') || ($structure === 'main' && $type === 'workout'))>
                <span class="stage-toggle-add">+ Adicionar {{ $label }}</span>
                <span class="stage-toggle-remove">Remover {{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>
    @php($position = 0)
    @foreach($types as $type => $label)
        @php($block = $byType->get($type, ['type' => $type, 'timer_type' => $type === 'workout' ? ($modality->slug === 'functional' ? 'circuit' : 'for_time') : 'none']))
        @php($enabled = $structure === 'main' ? $type === 'workout' : ($byType->has($type) || ($newWorkout && $type === 'workout')))
        @php($index = $enabled ? $position++ : 100 + $loop->index)
        <fieldset class="stage-fields" data-stage="{{ $type }}" @disabled(! $enabled) @if(! $enabled) hidden @endif><x-workouts.block-fields :index="$index" :block="$block" :modality="$modality" /></fieldset>
    @endforeach
    <script type="application/json" data-timer-registry>@json(\App\Workouts\TimerConfiguration::registry())</script>
    <datalist id="skill-suggestions">@foreach(['Snatch', 'Clean', 'Clean & Jerk', 'Deadlift', 'Squat', 'Pull-up', 'Muscle-up', 'HSPU', 'Handstand Walk', 'Toes to Bar'] as $skill)<option value="{{ $skill }}">@endforeach</datalist>
    <datalist id="station-suggestions">@foreach(['SkiErg', 'Sled Push', 'Sled Pull', 'Burpee Broad Jumps', 'Row', 'Farmers Carry', 'Sandbag Lunges', 'Wall Balls', 'Running'] as $exercise)<option value="{{ $exercise }}">@endforeach</datalist>
</form>
<x-workouts.presentation-check :box="$box" :modality="$modality->name" :preparation="$values['presentation_options']['preparation_seconds'] ?? null" form-id="workout-editor" />
<x-workouts.load-library :is-admin="auth()->user()->role === 'admin'" />
@endsection
