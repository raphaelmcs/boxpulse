@props(['slug'])
<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@if($slug === 'crossfit')<path d="M18 32h28M9 20v24m8-28v32m30-32v32m8-28v24M5 27v10m54-10v10"/>
@elseif($slug === 'hyrox')<circle cx="40" cy="10" r="5"/><path d="m29 22 10-4 9 11 9 3M15 28l12-4 7 13 11 4 4 13M33 36 23 49H9M10 15h13M5 21h11"/>
@else<circle cx="32" cy="10" r="5"/><path d="M32 20v20m-19-18 19 7 19-7M32 40 18 55m14-15 14 15"/>
@endif
</svg>
