@props(['month', 'calendarDays', 'markedDates', 'today', 'selected', 'start', 'end', 'filter', 'minDate', 'maxDate'])
@php
    $navigation = ['date' => $selected->toDateString(), 'modality' => $filter];
    $previousMonth = $month->subMonth()->format('Y-m');
    $nextMonth = $month->addMonth()->format('Y-m');
@endphp
<aside class="schedule-month" aria-labelledby="schedule-month-title">
    <div class="schedule-month-heading">
        <h2 id="schedule-month-title">{{ $month->locale('pt_BR')->translatedFormat('F Y') }}</h2>
        <a class="text-link" href="{{ route('schedule', ['modality' => $filter]) }}">Hoje</a>
    </div>
    <nav class="schedule-month-navigation" aria-label="Navegação do calendário mensal">
        @if($previousMonth >= substr($minDate, 0, 7))
            <a class="secondary-button" href="{{ route('schedule', [...$navigation, 'month' => $previousMonth]) }}" aria-label="Mês anterior">←</a>
        @else
            <button type="button" class="secondary-button" aria-label="Mês anterior" disabled>←</button>
        @endif
        @if($nextMonth <= substr($maxDate, 0, 7))
            <a class="secondary-button" href="{{ route('schedule', [...$navigation, 'month' => $nextMonth]) }}" aria-label="Próximo mês">→</a>
        @else
            <button type="button" class="secondary-button" aria-label="Próximo mês" disabled>→</button>
        @endif
    </nav>
    <table class="schedule-month-grid" aria-labelledby="schedule-month-title">
        <thead>
            <tr>
                @foreach(['Seg' => 'Segunda-feira', 'Ter' => 'Terça-feira', 'Qua' => 'Quarta-feira', 'Qui' => 'Quinta-feira', 'Sex' => 'Sexta-feira', 'Sáb' => 'Sábado', 'Dom' => 'Domingo'] as $short => $name)
                    <th scope="col" aria-label="{{ $name }}">{{ $short }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(array_chunk($calendarDays, 7) as $week)
                <tr>
                    @foreach($week as $day)
                        @php
                            $date = $day->toDateString();
                            $isSelected = $day->isSameDay($selected);
                            $isToday = $day->isSameDay($today);
                            $isInWeek = $day->betweenIncluded($start, $end);
                            $modalityCount = $markedDates[$date] ?? 0;
                            $workoutLabel = $modalityCount > 1 ? ", com treinos em $modalityCount modalidades" : ($modalityCount ? ', com treinos' : '');
                            $label = $day->locale('pt_BR')->translatedFormat('l, j \d\e F \d\e Y').$workoutLabel.($isSelected ? ', data selecionada' : '').($isInWeek ? ', semana exibida' : '');
                        @endphp
                        <td @class(['month-in-week' => $isInWeek, 'month-adjacent' => ! $day->isSameMonth($month)])>
                            @if($date >= $minDate && $date <= $maxDate)
                                <a href="{{ route('schedule', ['date' => $date, 'modality' => $filter]) }}" data-month-date="{{ $date }}"
                                    @class(['month-day', 'month-selected' => $isSelected, 'month-today' => $isToday])
                                    @if($isToday) aria-current="date" @endif aria-label="{{ $label }}">
                                    {{ $day->day }}
                                    @if($modalityCount)
                                        <span @class(['month-workouts-dot', 'month-workouts-multiple' => $modalityCount > 1]) aria-hidden="true"></span>
                                    @endif
                                </a>
                            @else
                                <span class="month-day">{{ $day->day }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="month-calendar-key"><span class="month-workouts-dot" aria-hidden="true"></span>Com treinos</p>
</aside>
