@extends('layouts.app')
@section('title', 'Cadastre sua box')
@section('content')
<div class="registration-layout">
    <section class="registration-intro"><p class="eyebrow">O PRIMEIRO PASSO</p><h1>Uma nova<br>energia para<br><span>sua box.</span></h1><p class="hero-copy">Crie o acesso do administrador principal.<br>Vamos começar pela sua equipe.</p><a class="back-link" href="{{ route('login') }}">← Já tenho uma conta</a></section>
    <section class="auth-card registration-card">
        <p class="eyebrow muted">01 / DADOS DA BOX</p><h2>Comece pelo essencial.</h2>
        <form method="POST" action="{{ route('register.store') }}" class="form-stack" enctype="multipart/form-data" data-registration-theme>@csrf
            <div><label for="box_name">Nome da box</label><input id="box_name" name="box_name" value="{{ old('box_name') }}" required maxlength="120" autocomplete="organization" placeholder="Ex.: Box Horizonte">@error('box_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="head_coach_name">Nome do Head Coach</label><input id="head_coach_name" name="head_coach_name" value="{{ old('head_coach_name') }}" required maxlength="120">@error('head_coach_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="name">Nome do administrador</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">@error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="username">Username de acesso</label><input id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="40" autocomplete="username" placeholder="Ex.: coach.horizonte"><p class="input-hint">Letras, números, ponto, hífen ou sublinhado.</p>@error('username')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div class="form-columns"><div><label for="password">Senha</label><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></div><div><label for="password_confirmation">Confirme a senha</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></div></div>
            <p class="input-hint">Use ao menos 12 caracteres, com maiúsculas, minúsculas e números.</p>@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <details class="registration-identity"><summary>Logo, cores e fuso horário</summary><div class="form-stack">
                <label>Logo da box<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small class="input-hint">PNG, JPG ou WebP até 2 MB. Também pode ser adicionada depois.</small></label>
                @foreach(['primary_color' => ['Cor primária', '#C8FA3C'], 'secondary_color' => ['Cor secundária', '#38BDF8']] as $field => [$label, $default])<label>{{ $label }}<input name="{{ $field }}" value="{{ is_string(old($field, $default)) ? old($field, $default) : $default }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" required></label>@endforeach
                <label>Fuso horário<select name="timezone" required>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', 'America/Sao_Paulo') === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
            </div></details>
            <x-workouts.modality-options :modalities="$modalities" heading="Quais modalidades sua box oferece?" />
            <button class="primary-button" type="submit">Criar minha box <span>↗</span></button>
        </form>
    </section>
</div>
@endsection
