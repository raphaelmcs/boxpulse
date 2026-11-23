@props(['text' => null, 'styles' => []])
@if(empty($styles))
{{ $text }}
@else
@foreach(\App\Workouts\WorkoutTextStyle::lines($text) as $index => $line)
<span style="{{ \App\Workouts\WorkoutTextStyle::css($styles[$index] ?? null) }}">{{ $line }}</span>{{ $loop->last ? '' : "\n" }}
@endforeach
@endif
