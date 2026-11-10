@props(['box', 'today', 'clock' => false, 'heading' => 'Sua próxima aula começa aqui.', 'kicker' => 'BOXPULSE / SUA BOX'])
<section class="box-workspace-identity" aria-label="Identidade da box">
    <div class="dashboard-logo-frame">@if($box->logo_path)<img class="dashboard-box-logo" src="{{ route('box.logo') }}" alt="Logo de {{ $box->name }}">@else<span class="dashboard-box-initial" aria-hidden="true">{{ mb_substr($box->name, 0, 1) }}</span>@endif</div>
    <div class="workspace-box-info"><p class="eyebrow">{{ $kicker }}</p><h1>{{ $box->name }}</h1><p class="muted">{{ $heading }}</p></div>
    <div class="date-card"><span>{{ $today->locale('pt_BR')->translatedFormat('l') }}</span><strong>{{ $today->format('d.m.Y') }}</strong>@if($clock)<time data-box-clock data-timezone="{{ $box->timezone }}" data-server-ms="{{ now('UTC')->getTimestampMs() }}">{{ $today->format('H:i:s') }}</time>@endif<small>{{ $box->timezone }}</small></div>
</section>
