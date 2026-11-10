@extends('layouts.app')
@section('title', 'Escolha seu ambiente')
@section('workspace', 'selection')
@section('content')
<x-feedback />
<x-box-identity :box="$box" :today="$today" heading="Olá! O que vamos fazer agora?" kicker="SUA BOX / SEU RITMO" />
<div class="workspace-intro"><p class="eyebrow">BEM-VINDO AO BOXPULSE</p><h2>Escolha seu ambiente<span>.</span></h2></div>
<div class="workspace-choices">
    <a class="workspace-choice" href="{{ route('manage.dashboard') }}"><span class="workspace-choice-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><rect x="7" y="10" width="34" height="32" rx="4"/><path d="M15 6v10M33 6v10M7 22h34M15 29h7M27 29h7M15 35h7"/></svg></span><span class="eyebrow muted">PLANEJAR E ORGANIZAR</span><h2>Gerenciar treinos</h2><p>Cadastre, edite e organize os treinos da sua box.</p><span class="workspace-choice-action">Entrar na Gestão <span aria-hidden="true">↗</span></span></a>
    <a class="workspace-choice classroom-choice" href="{{ route('coach.today') }}"><span class="workspace-choice-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><circle cx="24" cy="27" r="16"/><path d="M18 4h12M24 5v6M36 12l5 5M24 17v11l7 4"/></svg></span><span class="eyebrow muted">CONDUZIR E APRESENTAR</span><h2>Iniciar aula</h2><p>Escolha o treino do dia e controle a apresentação.</p><span class="workspace-choice-action">Entrar no Modo Aula <span aria-hidden="true">↗</span></span></a>
</div>
@endsection
