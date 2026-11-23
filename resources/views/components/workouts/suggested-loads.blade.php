@props(['loads' => null, 'entries' => null, 'live' => false])
@php
    $legacy = \App\Workouts\SuggestedLoads::normalize($loads);
    $rows = array_filter([$legacy, ...($entries ?? [])], fn ($entry) => \App\Workouts\SuggestedLoads::normalize($entry) !== null);
@endphp
@if($rows || $live)
<section class="suggested-loads" @if($live) data-suggested-loads @endif @if(!$rows) hidden @endif aria-label="Cargas sugeridas">
    <h3 class="suggested-loads-heading" @if($live) data-load-heading @endif>{{ $rows ? 'CARGAS SUGERIDAS' : '' }}</h3>
    <div class="suggested-load-entries" @if($live) data-live-load-entries @endif>
        @foreach($rows as $entry)
            <x-workouts.suggested-load-row :entry="$entry" />
        @endforeach
    </div>
    @if($live)<template data-load-row-template><x-workouts.suggested-load-row /></template>@endif
</section>
@endif
