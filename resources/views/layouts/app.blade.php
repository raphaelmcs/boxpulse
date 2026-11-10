<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'Sua próxima evolução') · BOXPULSE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php($environment = trim($__env->yieldContent('workspace', 'manage')))
<body class="environment-{{ $environment }}" style="{{ \App\Support\BoxTheme::variables($box ?? null) }}">
    <div class="app-shell">
        <header class="app-header">
            <a class="brand" href="{{ route('home') }}" aria-label="BOXPULSE, início"><span class="brand-mark" aria-hidden="true">ϟ</span> BOX<span>PULSE</span></a>
            <div class="header-right">
                @if(isset($box) && $environment === 'manage' && ! request()->routeIs('dashboard', 'manage.dashboard'))<a class="header-box-identity" href="{{ route('manage.dashboard') }}">@if($box->logo_path)<img src="{{ route('box.logo') }}" class="header-box-logo" alt="Logo de {{ $box->name }}">@endif<span>{{ $box->name }}</span></a>@endif
                @if(auth()->check() && $environment !== 'selection')<span class="edition">{{ $environment === 'coach' ? 'MODO AULA' : 'GESTÃO DA BOX' }} <span class="status-dot"></span></span>@endif
                @auth
                    <form action="{{ route('logout') }}" method="POST">@csrf<button class="text-button" type="submit">Sair da conta ↗</button></form>
                @endauth
            </div>
        </header>
        <main>@yield('content')</main>
        <footer>BOXPULSE <span>Feito para o ritmo da sua box.</span><span>PERFORMANCE / V.03</span></footer>
    </div>
</body>
</html>
