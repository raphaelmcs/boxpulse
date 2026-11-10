@extends('layouts.app')
@section('title', 'Visão geral')
@section('content')
<x-workouts.navigation />
<section class="dashboard-identity" aria-label="Identidade da box">
    <div class="dashboard-logo-frame">@if($box->logo_path)<img class="dashboard-box-logo" src="{{ route('box.logo') }}" alt="Logo de {{ $box->name }}">@else<span class="dashboard-box-initial" aria-hidden="true">{{ mb_substr($box->name, 0, 1) }}</span>@endif</div>
    <div class="dashboard-box-info"><p class="eyebrow">SUA BOX / SUA COMUNIDADE</p><h1>{{ $box->name }}</h1>@if($box->head_coach_name)<p class="box-head-coach">Head Coach · {{ $box->head_coach_name }}</p>@endif<p class="muted">Olá, {{ auth()->user()->name }}. Prepare a próxima evolução.</p></div>
    <div class="date-card"><span>{{ $today->locale('pt_BR')->translatedFormat('l') }}</span><strong>{{ $today->format('d.m.Y') }}</strong><small>{{ $box->timezone }}</small></div>
    <div class="dashboard-identity-actions"><a class="secondary-button" href="{{ route('schedule') }}">Ver programação →</a>
        @if($workouts->isNotEmpty())<a class="primary-button compact" href="{{ route('coach.today') }}">Iniciar aula de hoje ↗</a><p class="input-hint">Escolha o treino no Modo Aula.</p>
        @else<a class="primary-button compact" href="{{ route('workouts.create', ['date' => $today->toDateString()]) }}">Cadastrar treino de hoje ↗</a>@endif
    </div>
</section>
<div class="section-heading"><h2>Escolha a modalidade</h2><span class="eyebrow muted">SUA PRÓXIMA AULA</span></div>
<div class="modality-grid">@foreach($modalities as $modality)<a class="modality-card modality-{{ $modality->slug }}" href="{{ route('modalities.show', $modality) }}"><span class="modality-number">0{{ $loop->iteration }} / MODALIDADE</span><div class="modality-symbol"><x-workouts.modality-icon :slug="$modality->slug" /></div><h3>{{ $modality->name }}</h3><p>Cadastrar, programar e preparar a aula <span aria-hidden="true">↗</span></p></a>@endforeach</div>
<div class="section-heading" id="today-workouts"><h2>Treinos de hoje</h2><a class="text-link" href="{{ route('schedule') }}">Ver programação →</a></div>
@if($workouts->isEmpty())<section class="empty-state"><span class="empty-icon" aria-hidden="true">+</span><div><h3>Seu próximo treino começa aqui.</h3><p>Ainda não há treinos publicados para hoje. Cadastre uma aula ou publique um rascunho na programação.</p><a class="text-link" href="{{ route('workouts.create') }}">Cadastrar treino →</a></div></section>
@else @foreach($workouts->groupBy('training_modality_id') as $group)<section class="today-group"><h3>{{ $group->first()->modality->name }}</h3>@foreach($group as $workout)<article class="workout-row"><div><span class="status-badge published">Publicado</span><h3><a href="{{ route('workouts.show', $workout) }}">{{ $workout->title }}</a></h3></div><div class="button-row"><a class="secondary-button" href="{{ route('workouts.show', $workout) }}">Visualizar blocos</a><a class="secondary-button" href="{{ route('workouts.presentation', $workout) }}">Revisar apresentação</a><a class="primary-button compact" href="{{ route('coach.today') }}#workout-{{ $workout->id }}">Abrir no Modo Aula ↗</a></div></article>@endforeach</section>@endforeach @endif
@if($drafts->isNotEmpty())<div class="section-heading"><h2>Rascunhos de hoje</h2></div>@foreach($drafts as $draft)<a class="workout-row" href="{{ route('workouts.edit', $draft) }}"><div><span class="status-badge draft">Rascunho</span><h3>{{ $draft->title }}</h3><p class="input-hint">{{ $draft->modality->name }} · Complete e publique para disponibilizar a aula.</p></div><span>Editar treino →</span></a>@endforeach
@endif
<div class="section-heading"><h2>Próximos treinos</h2><a class="text-link" href="{{ route('workouts.index') }}">Histórico de treinos →</a></div>
@forelse($upcoming as $next)<a class="workout-row" href="{{ route('workouts.show', $next) }}"><div><span class="status-badge {{ $next->status }}">{{ $next->status === 'published' ? 'Publicado' : 'Rascunho' }}</span><h3>{{ $next->title }}</h3><p class="input-hint">{{ $next->scheduled_for->format('d/m/Y') }} · {{ $next->modality->name }}</p></div><span>Ver treino →</span></a>@empty<div class="empty-state"><p>Nenhum próximo treino cadastrado. Organize as próximas aulas na programação semanal.</p><a class="text-link" href="{{ route('schedule') }}">Abrir programação →</a></div>@endforelse
@endsection
