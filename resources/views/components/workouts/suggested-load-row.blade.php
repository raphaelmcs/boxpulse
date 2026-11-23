@props(['entry' => []])
@php
    $name = implode(' · ', array_unique(array_filter([$entry['equipment'] ?? null, $entry['movement'] ?? null])));
    $implement = \App\Workouts\SuggestedLoads::IMPLEMENTS[$entry['implement'] ?? ''] ?? null;
    $unit = $entry['unit'] ?? null;
@endphp
<div class="suggested-load-entry" data-load-row>
    <div class="suggested-load-name">
        <strong data-load-name>{{ $name ?: 'Carga geral' }}</strong>
        <small data-load-implement @if(!$implement) hidden @endif>{{ $implement }}</small>
    </div>
    <div class="suggested-load-cards">
        @foreach(['rx' => 'RX', 'intermediate' => 'INTER', 'scale' => 'SCALE'] as $key => $label)
            @php($value = trim((string) ($entry[$key] ?? '')) !== '' ? $entry[$key] : null)
            @php($pairUnit = $unit && $value !== null && preg_match('/\A[0-9]+(?:[.,][0-9]+)?\s*\/\s*[0-9]+(?:[.,][0-9]+)?\z/', $value) ? $unit : null)
            @php($writtenPair = $value !== null && preg_match('/\A([0-9]+(?:[.,][0-9]+)?\s*\/\s*[0-9]+(?:[.,][0-9]+)?)\s*(kg|lbs?)\z/i', $value, $pair) ? $pair : null)
            <div class="suggested-load-card" data-load-level="{{ $key }}" @if($value === null) hidden @endif>
                <span>{{ $label }}</span>
                <div class="suggested-load-amount"><strong data-load-value>{{ $writtenPair[1] ?? ($value === null ? '' : \App\Workouts\SuggestedLoads::display($value, $unit)) }}</strong> <small data-load-unit @if(!$writtenPair && !$pairUnit) hidden @endif>{{ $writtenPair[2] ?? $pairUnit }}</small></div>
            </div>
        @endforeach
    </div>
</div>
