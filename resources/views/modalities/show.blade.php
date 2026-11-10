@extends('layouts.app')
@section('title', $modality->name)
@section('content')
<x-workouts.navigation />
<div class="page-heading"><p class="eyebrow">PROGRAME A PRÓXIMA EVOLUÇÃO</p><h1>{{ $modality->name }}<span>.</span></h1><p class="hero-copy">Escolha como quer preparar a sua aula.</p></div>
<div class="choice-grid">
<a class="choice-card" href="{{ route('workouts.create', ['modality' => $modality->slug, 'structure' => 'full']) }}"><span class="eyebrow">01 / AULA COMPLETA</span><h2>Cadastrar treino completo ↗</h2><p>Aquecimento, {{ $modality->slug === 'crossfit' ? 'skill' : 'preparação técnica' }} e {{ $modality->slug === 'functional' ? 'circuito' : 'workout' }} em um só lugar.</p></a>
<a class="choice-card" href="{{ route('workouts.create', ['modality' => $modality->slug, 'structure' => 'main']) }}"><span class="eyebrow">02 / DIRETO AO PONTO</span><h2>Somente treino principal ↗</h2><p>Um formulário rápido para quem já sabe o treino que quer entregar.</p></a>
<a class="choice-card" href="{{ route('schedule', ['modality' => $modality->slug]) }}"><span class="eyebrow">03 / OLHE À FRENTE</span><h2>Consultar programação →</h2><p>Planeje a semana e organize quantas aulas precisar por dia.</p></a>
<a class="choice-card" href="{{ route('workouts.index', ['modality' => $modality->slug]) }}"><span class="eyebrow">04 / REPERTÓRIO</span><h2>Consultar treinos anteriores →</h2><p>Revisite suas aulas e copie os treinos para novas datas.</p></a>
</div>
@endsection
