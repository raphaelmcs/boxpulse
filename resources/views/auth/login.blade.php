@extends('layouts.app')
@section('title', 'Acesse sua box')
@section('content')
<div class="auth-grid">
    <section class="hero">
        <p class="eyebrow"><span class="status-dot"></span> SUA BOX. SEU RITMO.</p>
        <h1>O próximo<br>nível começa<br><span>aqui.</span></h1>
        <p class="hero-copy">Um espaço para preparar a aula.<br>Uma experiência para mover sua comunidade.</p>
        <div class="sport-tags"><span>01 / CROSSFIT</span><span>02 / HYROX</span><span>03 / FUNCIONAL</span></div>
        <div class="pulse-line" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    </section>
    <section class="auth-card">
        <p class="eyebrow muted">BEM-VINDO DE VOLTA</p>
        <h2>Entre na sua box.</h2>
        <p class="muted">Acesse com seu username e senha.</p>
        <form method="POST" action="{{ route('login.store') }}" class="form-stack">
            @csrf
            <div><label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" autocomplete="username" placeholder="seu.username" required maxlength="40" autofocus aria-describedby="username-error">@error('username')<p class="field-error" id="username-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="password">Senha</label><input id="password" type="password" name="password" autocomplete="current-password" placeholder="Sua senha" required maxlength="72">@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <button class="primary-button" type="submit">Acessar minha box <span>↗</span></button>
        </form>
        <p class="auth-switch">Primeira vez aqui? <a href="{{ route('register') }}">Cadastre sua box →</a></p>
        <div class="card-note"><span class="status-dot"></span> Seu espaço de treino começa com uma conexão.</div>
    </section>
</div>
@endsection
