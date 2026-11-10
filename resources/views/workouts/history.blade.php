@extends('layouts.app')
@section('title', 'Treinos anteriores')
@section('content')
<x-workouts.navigation />
<div class="page-heading"><p class="eyebrow">SEU REPERTÓRIO</p><h1>Treinos anteriores<span>.</span></h1><p class="hero-copy">Consulte aulas anteriores a hoje no fuso {{ $box->timezone }}.</p></div><form method="GET" class="history-filter button-row"><label>Modalidade<select name="modality"><option value="">Todas as modalidades</option>@foreach($modalities as $modality)<option value="{{ $modality->slug }}" @selected($filter === $modality->slug)>{{ $modality->name }}</option>@endforeach</select></label><button class="secondary-button" type="submit">Filtrar histórico</button></form>
@forelse($workouts as $workout)<a class="workout-row" href="{{ route('workouts.show', $workout) }}"><span>{{ $workout->scheduled_for->format('d/m/Y') }} · {{ $workout->modality->name }}</span><strong>{{ $workout->title }}</strong><x-workouts.modality-status :workout="$workout" /><span class="status-badge {{ $workout->status }}">{{ $workout->status === 'published' ? 'Publicado' : 'Rascunho' }}</span></a>@empty<div class="empty-state"><p>Nenhum treino anterior encontrado nesta modalidade.</p><a class="text-link" href="{{ route('workouts.create') }}">Cadastrar treino →</a></div>@endforelse
{{ $workouts->links() }}
@endsection
