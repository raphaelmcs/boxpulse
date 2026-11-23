@extends('layouts.app')
@section('title', 'Programação semanal')
@section('content')
<x-workouts.navigation />
@php
    $minDate = '2000-01-01';
    $maxDate = '2100-12-31';
    $previousWeek = max($minDate, $start->subWeek()->toDateString());
    $nextWeek = $start->addWeek()->toDateString();
@endphp
<div class="schedule-layout">
    <div class="schedule-main">
        <div class="page-heading">
            <p class="eyebrow">Gestão da Box · {{ $box->timezone }}</p>
            <h1>Programação <span>semanal.</span></h1>
        </div>
        <div class="schedule-toolbar">
            <nav class="button-row" aria-label="Navegação da semana">
                @if($previousWeek < $start->toDateString())
                    <a class="secondary-button" href="{{ route('schedule', ['date' => $previousWeek, 'modality' => $filter]) }}" aria-label="Semana anterior">←</a>
                @else
                    <button type="button" class="secondary-button" aria-label="Semana anterior" disabled>←</button>
                @endif
                <strong>{{ $start->format('d/m') }} — {{ $end->format('d/m/Y') }}</strong>
                @if($nextWeek <= $maxDate)
                    <a class="secondary-button" href="{{ route('schedule', ['date' => $nextWeek, 'modality' => $filter]) }}" aria-label="Próxima semana">→</a>
                @else
                    <button type="button" class="secondary-button" aria-label="Próxima semana" disabled>→</button>
                @endif
                <a class="text-link" href="{{ route('schedule', ['modality' => $filter]) }}">Hoje</a>
            </nav>
            <form method="GET" action="{{ route('schedule') }}" class="button-row">
                <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <label class="sr-only" for="modality-filter">Filtrar modalidade</label>
                <select id="modality-filter" name="modality">
                    <option value="">Todas as modalidades</option>
                    @foreach($modalities as $modality)
                        <option value="{{ $modality->slug }}" @selected($filter === $modality->slug)>{{ $modality->name }}{{ in_array($modality->id, $activeModalityIds, true) ? '' : ' · desativada (consulta)' }}</option>
                    @endforeach
                </select>
                <button class="text-button" type="submit">Filtrar</button>
            </form>
        </div>

        <div class="week-grid">
            @foreach($days as $day)
                @php($date = $day->toDateString())
                <section @class(['week-day', 'is-today' => $day->isSameDay($today), 'is-selected' => $day->isSameDay($selected)]) data-date="{{ $date }}">
                    <header>
                        <h2>{{ $day->locale('pt_BR')->translatedFormat('l') }}</h2>
                        <strong>{{ $day->format('d') }}</strong>
                    </header>
                    <div class="day-workouts">
                        @forelse($workouts->get($date, collect())->groupBy('training_modality_id') as $group)
                            <div class="day-modality">
                                <h3>{{ $group->first()->modality->name }}</h3>
                                @foreach($group as $workout)
                                    <a class="calendar-workout" href="{{ route('workouts.show', $workout) }}">
                                        <span class="status-badge {{ $workout->status }}">{{ $workout->status === 'published' ? 'Publicado' : 'Rascunho' }}</span>
                                        <strong>{{ $workout->title }}</strong>
                                        <x-workouts.modality-status :workout="$workout" :enabled="in_array($workout->training_modality_id, $activeModalityIds, true)" />
                                        <small>Visualizar e gerenciar →</small>
                                    </a>
                                @endforeach
                            </div>
                        @empty
                            <p class="empty-day">Dia livre para uma nova aula.</p>
                        @endforelse
                    </div>
                    @if($date >= $minDate && $date <= $maxDate)
                        <a class="add-day" href="{{ route('workouts.create', ['date' => $date, 'modality' => $createModality, 'structure' => 'full']) }}">+ Adicionar treino</a>
                    @endif
                </section>
            @endforeach
        </div>
        @if($start->toDateString() >= $minDate && $start->toDateString() <= '2100-12-24')
            <details class="copy-week-panel">
                <summary>Duplicar programação desta semana</summary>
                <p>Todos os treinos das modalidades ativas serão copiados, respeitando os dias da semana, sempre como rascunhos. O filtro acima não limita esta operação.</p>
                <form method="POST" action="{{ route('schedule.copy') }}" class="form-stack">
                    @csrf
                    <input type="hidden" name="source_date" value="{{ $start->toDateString() }}">
                    <input type="hidden" name="modality" value="{{ $filter }}">
                    <div class="field-grid two-columns">
                        <label>Uma data da semana de destino<input type="date" name="target_date" value="{{ old('target_date', min($nextWeek, '2100-12-24')) }}" required min="{{ $minDate }}" max="2100-12-24"></label>
                        <label>Se já houver treinos no destino<select name="conflict_policy" required><option value="abort" @selected(old('conflict_policy') !== 'append')>Cancelar a cópia inteira</option><option value="append" @selected(old('conflict_policy') === 'append')>Adicionar como rascunhos e preservar existentes</option></select></label>
                    </div>
                    <label class="checkbox-label"><input type="checkbox" name="confirmation" value="1" required>Confirmo a cópia de toda a semana como rascunhos, sem substituir treinos existentes.</label>
                    <button type="submit" class="secondary-button">Confirmar cópia semanal</button>
                </form>
            </details>
        @endif
    </div>
    <x-schedule.month-calendar :$month :$calendarDays :$markedDates :$today :$selected :$start :$end :$filter :$minDate :$maxDate />
</div>
@endsection
