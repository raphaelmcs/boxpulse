@props(['loads' => null, 'live' => false])
@php($loads = \App\Workouts\SuggestedLoads::normalize($loads))
@if($loads || $live)
<section class="suggested-loads" @if($live) data-suggested-loads @endif @if(! $loads) hidden @endif aria-label="Cargas sugeridas">
    <div class="suggested-loads-heading"><span>CARGAS SUGERIDAS</span><strong @if($live) data-load-equipment @endif>{{ $loads['equipment'] ?? '' }}</strong></div>
    <div class="suggested-load-cards">@foreach(\App\Workouts\SuggestedLoads::LEVELS as $key => $label)
        @if($live || ($loads[$key] ?? null) !== null)<div class="suggested-load-card" @if($live) data-load-level="{{ $key }}" @endif @if(($loads[$key] ?? null) === null) hidden @endif><span>{{ $label }}</span><strong @if($live) data-load-value @endif>{{ isset($loads[$key]) ? \App\Workouts\SuggestedLoads::display($loads[$key], $loads['unit']) : '' }}</strong></div>@endif
    @endforeach</div>
</section>
@endif
