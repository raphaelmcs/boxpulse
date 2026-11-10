@extends('layouts.app')
@section('title', 'Aulas de hoje')
@section('workspace', 'coach')
@section('content')
<x-sessions.navigation />
<x-box-identity :box="$box" :today="$today" :clock="true" heading="Escolha o treino. Prepare a apresentação. Comece no seu ritmo." kicker="MODO AULA / SUA EQUIPE EM MOVIMENTO" />
<div class="section-heading"><h2>Aulas de hoje</h2><a class="text-link" href="{{ route('coach.today', ['modality' => $filter]) }}">Atualizar lista ↻</a></div>
@if($modalities->count() > 1)<nav class="classroom-filters" aria-label="Filtrar aulas por modalidade"><a href="{{ route('coach.today') }}" @class(['is-active' => ! $filter]) @if(! $filter) aria-current="page" @endif>Todas</a>@foreach($modalities as $modality)<a href="{{ route('coach.today', ['modality' => $modality->slug]) }}" @class(['is-active' => $filter === $modality->slug]) @if($filter === $modality->slug) aria-current="page" @endif>{{ $modality->name }}</a>@endforeach</nav>@endif
<p class="notice" data-day-changed hidden>O dia mudou. Atualize a lista para ver as aulas de hoje.</p>
<div class="classroom-cards">
@forelse($workouts as $workout)
    @php
        $active = $workout->activeSession;
        $last = $workout->latestSession;
        $begun = $active?->hasExecutionActivity();
        $newRun = ! $active && $last;
        $main = $workout->blocks->firstWhere('type', 'workout') ?? $workout->blocks->first();
    @endphp
    <article class="classroom-card" id="workout-{{ $workout->id }}" data-workout-id="{{ $workout->id }}">
        <div class="classroom-card-top"><span class="eyebrow">{{ $workout->modality->name }}</span><span class="status-badge {{ $active ? 'published' : 'draft' }}">{{ $active ? ($begun ? 'Aula em andamento' : 'Preparação pronta') : ($last ? 'Última aula encerrada' : 'Pronta para preparar') }}</span></div>
        <h3>{{ $workout->title }}</h3><p class="classroom-structure">{{ $workout->blocks->map(fn ($block) => ['warmup' => 'Warm-up', 'skill' => 'Skill', 'workout' => 'Workout'][$block->type])->join(' + ') }} · {{ $workout->blocks->count() }} {{ $workout->blocks->count() === 1 ? 'bloco' : 'blocos' }}</p>
        <p class="classroom-timing">{{ \App\Workouts\WorkoutPresentation::mainTiming($main) }}</p>
        @if($active)<p class="input-hint">Aula #{{ $active->id }} · preparada em {{ $active->created_at->setTimezone($box->timezone)->format('d/m H:i') }}@if($active->workout_version !== $workout->content_version) · {{ $begun ? 'Conteúdo original preservado.' : 'O conteúdo será atualizado ao preparar.' }}@endif</p>
        @elseif($last)<p class="input-hint">Última aula #{{ $last->id }} · {{ $last->finished_at?->setTimezone($box->timezone)->format('d/m H:i') }} · <a class="text-link" href="{{ route('sessions.summary', $last) }}">Ver resumo</a></p>@endif
        @if($last)<a class="text-link" href="{{ route('coach.history', $workout) }}">Aulas anteriores deste treino →</a>@endif
        @if($begun)<a class="primary-button compact" href="{{ route('sessions.control', $active) }}">Retomar aula →</a>
        @else<form method="POST" action="{{ route('coach.prepare', $workout) }}" class="classroom-prepare-form form-stack" data-prepare-class>@csrf
            @if($newRun)<input type="hidden" name="previous_session_id" value="{{ $last->id }}"><label class="checkbox-label"><input type="checkbox" name="new_session_confirmation" value="1" required>Confirmo iniciar outra aula. A sessão anterior permanece no histórico.</label>@endif
            <button type="submit" class="primary-button compact">{{ $active ? 'Continuar preparação' : ($last ? 'Iniciar nova sessão' : 'Preparar aula') }} ↗</button><span class="input-hint" data-prepare-feedback role="status">O cronômetro começa apenas pelo controle da aula.</span>
        </form>@endif
    </article>
@empty
    <section class="empty-state classroom-empty"><div><h3>Nenhuma aula programada para hoje.</h3>@if($filter)<p>Não há treinos publicados nesta modalidade. Você pode ver todas as aulas ou consultar a programação.</p><a class="text-link" href="{{ route('coach.today') }}">Ver todas as aulas →</a>@else<p>As aulas aparecem aqui depois que os treinos são publicados na Gestão.</p>@endif<div class="button-row"><a class="secondary-button" href="{{ route('schedule') }}">Consultar programação</a><a class="primary-button compact" href="{{ route('workouts.create') }}">Cadastrar novo treino ↗</a></div></div></section>
@endforelse
</div>
@if($ongoing->isNotEmpty())<section class="classroom-ongoing"><div class="section-heading"><h2>Aulas em andamento</h2><span class="input-hint">Continue uma aula já iniciada, inclusive de outro dia.</span></div>@foreach($ongoing as $running)<article class="workout-row"><div><strong>{{ $running->title }}</strong><p class="input-hint">Aula #{{ $running->id }} · {{ $running->modality_name }} · iniciada {{ $running->started_at?->setTimezone($box->timezone)->format('d/m H:i') ?? 'com troca de etapa' }}@if(! $box->offersModality($running->workout->training_modality_id)) · Modalidade desativada; esta aula pode ser concluída.@endif</p></div><a class="secondary-button" href="{{ route('sessions.control', $running) }}">Retomar aula #{{ $running->id }} →</a></article>@endforeach</section>@endif
@endsection
