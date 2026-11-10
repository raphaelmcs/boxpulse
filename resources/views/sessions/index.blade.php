@extends('layouts.app')
@section('title', 'Sessões de aula')
@section('workspace', ($classroom ?? false) ? 'coach' : 'manage')
@section('content')
@if($classroom ?? false)<x-sessions.navigation />@else<x-workouts.navigation />@endif
<div class="page-heading"><p class="eyebrow">AULAS / HISTÓRICO DE EXECUÇÃO</p><h1>{{ isset($workout) ? 'Aulas deste treino' : 'Sessões de aula' }}<span>.</span></h1>@isset($workout)<p class="hero-copy">{{ $workout->title }} · cada aula tem seu próprio número, horário e conteúdo preservado.</p>@endisset<p class="hero-copy">Retome o controle de aulas ativas ou consulte o histórico preservado.</p></div>
@forelse($sessions as $session)<article class="workout-row"><div><span class="status-badge {{ $session->status === 'active' ? 'published' : 'draft' }}">{{ $session->superseded_by_id ? 'Substituída' : ['active' => 'Em andamento / preparada', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'][$session->status] }}</span><h3>{{ $session->title }}</h3><p class="input-hint">Aula #{{ $session->id }} · {{ $session->created_at->setTimezone($box->timezone)->format('d/m/Y H:i') }} · {{ $session->modality_name }} · {{ $session->scheduled_for->format('d/m/Y') }}</p></div><div class="button-row"><a class="secondary-button" href="{{ route('sessions.control', $session) }}">Abrir controle / leitura</a><a class="text-link" href="{{ route('sessions.summary', $session) }}">Resumo →</a></div></article>@empty<div class="empty-state">Nenhuma sessão preparada. Escolha um treino publicado e clique em Iniciar apresentação.</div>@endforelse
{{ $sessions->links() }}
@endsection
