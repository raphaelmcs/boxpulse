@extends('layouts.app')
@section('title', 'Pré-visualização · '.$workout->title)
@section('content')
<x-workouts.modality-status :workout="$workout" /><div class="preview-toolbar"><a class="back-link" href="{{ route('workouts.show', $workout) }}">← Voltar ao treino</a><span class="preview-notice">PRÉ-VISUALIZAÇÃO · SEM EXECUÇÃO DE CRONÔMETROS</span>@if($workout->status === 'draft')<span class="status-badge draft">Rascunho</span>@endif</div>
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
<section class="presentation-preview"><header class="preview-heading"><div class="box-identity">@if($box->logo_path)<img class="box-logo" src="{{ route('box.logo') }}" alt="Logo de {{ $box->name }}">@else<span class="box-monogram" aria-hidden="true">{{ mb_substr($box->name, 0, 1) }}</span>@endif<div><p class="eyebrow">{{ $box->name }}</p><span>{{ $workout->modality->name }} · {{ $workout->scheduled_for->format('d.m.Y') }}</span></div></div><span class="preview-state">{{ $workout->status === 'published' ? 'AULA PREPARADA' : 'REVISÃO DO COACH' }}</span></header><h1>{{ $workout->title }}</h1><div class="preview-blocks">@forelse($workout->blocks as $block)<x-workouts.block-display :block="$block" />@empty<p class="muted">Adicione os blocos no editor antes de publicar.</p>@endforelse</div></section>
@if($workout->status === 'draft' && $box->offersModality($workout->training_modality_id))<section class="preview-publish"><p class="muted">Revise o conteúdo antes de publicar. Rascunhos não preparam a apresentação.</p><form method="POST" action="{{ route('workouts.publish', $workout) }}">@csrf<button class="primary-button compact" type="submit">Publicar treino ↗</button></form></section>@endif
<x-sessions.prepare :workout="$workout" />
@endsection
