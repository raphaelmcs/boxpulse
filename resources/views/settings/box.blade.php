@extends('layouts.app')
@section('title', 'Configurações da Box')
@section('content')
<x-workouts.navigation />
@php($fieldValue = fn (string $key, string $fallback): string => is_scalar(old($key, $fallback)) ? (string) old($key, $fallback) : $fallback)
<div class="page-heading"><p class="eyebrow">IDENTIDADE / SUA BOX</p><h1>Seu espaço.<br><span>Sua identidade.</span></h1><p class="hero-copy">Personalize a experiência da equipe e da TV. Campos com * são obrigatórios.</p></div>
<form method="POST" action="{{ route('box.settings.update') }}" enctype="multipart/form-data" class="settings-grid" data-box-settings>
@csrf @method('PUT')
<div class="settings-fields form-panel form-stack">
    <h2>Identidade da Box</h2>
    <label>Nome da box *<input name="name" value="{{ $fieldValue('name', $box->name) }}" required maxlength="120" data-box-name></label>
    <label>Logo da box<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-logo-input><small class="input-hint">PNG, JPG ou WebP. Até 2 MB, dimensão máxima 8192 × 8192. A imagem mantém suas proporções.</small></label>
    @if($box->logo_path)<label class="checkbox-label"><input type="checkbox" name="remove_logo" value="1" data-logo-remove>Remover logo atual</label>@endif
    @foreach(['primary_color' => 'Cor primária', 'secondary_color' => 'Cor secundária'] as $field => $label)
    <div class="color-field" data-color-field="{{ $field }}"><label for="{{ $field }}">{{ $label }} *</label><div class="color-inputs"><input type="color" aria-label="Selecionar {{ mb_strtolower($label) }}" value="{{ \App\Support\BoxTheme::color($fieldValue($field, $box->$field), '#C8FA3C') }}" data-color-picker><input id="{{ $field }}" name="{{ $field }}" value="{{ $fieldValue($field, $box->$field) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" required data-color-hex></div></div>
    @endforeach
    <h2>Informações Administrativas</h2>
    <label>Head Coach *<input name="head_coach_name" value="{{ $fieldValue('head_coach_name', $box->head_coach_name) }}" required maxlength="120"></label>
    <label>Nome do administrador *<input name="administrator_name" value="{{ $fieldValue('administrator_name', auth()->user()->name) }}" required maxlength="120"></label>
    <label>Fuso horário *<select name="timezone" required>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected($fieldValue('timezone', $box->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
    <x-workouts.modality-options :modalities="$modalities" :selected="$box->modalities()->pluck('training_modalities.id')->all()" />
    <p class="input-hint">Desativar preserva treinos e histórico. Aulas já iniciadas continuam; novas publicações, cópias e apresentações ficam bloqueadas.</p>
    <button class="primary-button" type="submit">Salvar configurações <span>↗</span></button>
</div>
<aside class="identity-preview" data-identity-preview><p class="eyebrow">PRÉVIA DA IDENTIDADE</p><img class="settings-logo" data-logo-preview @if($box->logo_path) src="{{ route('box.logo') }}" @else hidden @endif alt="Prévia da logo da box"><h2 data-box-name-preview>{{ $box->name }}</h2><p class="muted">A energia da sua box em cada tela.</p><div class="identity-timer">12:45</div><div class="identity-progress"><span></span></div><span class="identity-tag">ETAPA ATIVA · WORKOUT</span><button class="primary-button" type="button">Sua próxima evolução ↗</button><p class="input-hint">O contraste dos textos é ajustado automaticamente. As mudanças são aplicadas após salvar.</p></aside>
</form>
@endsection
