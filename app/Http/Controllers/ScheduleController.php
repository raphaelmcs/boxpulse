<?php

namespace App\Http\Controllers;

use App\Actions\CopyWorkoutWeek;
use App\Http\Requests\CopyWeekRequest;
use App\Models\TrainingModality;
use App\Models\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Workout::class);
        $filter = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'month' => ['nullable', 'date_format:Y-m', 'regex:/\A(?:20[0-9]{2}|2100)-[0-9]{2}\z/'],
            'modality' => ['nullable', 'exists:training_modalities,slug'],
        ]);
        $box = $request->user()->box;
        $today = CarbonImmutable::today($box->timezone);
        $selected = isset($filter['date']) ? CarbonImmutable::parse($filter['date'], $box->timezone) : $today;
        $start = $selected->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);
        $month = isset($filter['month']) ? CarbonImmutable::parse($filter['month'].'-01', $box->timezone) : $selected->startOfMonth();
        $calendarStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $calendarEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();

        $activeIds = $box->modalities()->pluck('training_modalities.id')->all();
        $modalities = TrainingModality::all()->sortBy(fn (TrainingModality $modality): bool => ! in_array($modality->id, $activeIds, true));
        $filteredModality = $modalities->firstWhere('slug', $filter['modality'] ?? null);
        $visibleWorkouts = Workout::query()->when($filteredModality, fn ($query) => $query->where('training_modality_id', $filteredModality->id));
        $workouts = (clone $visibleWorkouts)->with('modality')
            ->whereBetween('scheduled_for', [$start->toDateString(), $end->toDateString()])->orderBy('training_modality_id')->orderBy('id')
            ->get()->sortBy(fn (Workout $workout): bool => ! in_array($workout->training_modality_id, $activeIds, true))
            ->groupBy(fn (Workout $workout): string => $workout->scheduled_for->toDateString());
        $markedDates = $visibleWorkouts->whereIn('training_modality_id', $activeIds)
            ->whereBetween('scheduled_for', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
            ->distinct()->orderBy('scheduled_for')->get(['scheduled_for', 'training_modality_id'])
            ->countBy(fn (Workout $workout): string => $workout->scheduled_for->toDateString())->all();

        return view('schedule.index', [
            'box' => $box, 'today' => $today, 'selected' => $selected, 'start' => $start, 'end' => $end, 'month' => $month,
            'days' => collect(range(0, 6))->map(fn (int $day): CarbonImmutable => $start->addDays($day)),
            'calendarDays' => $calendarStart->daysUntil($calendarEnd)->toArray(),
            'markedDates' => $markedDates,
            'workouts' => $workouts, 'modalities' => $modalities, 'activeModalityIds' => $activeIds, 'filter' => $filter['modality'] ?? '',
            'createModality' => $filteredModality && in_array($filteredModality->id, $activeIds, true) ? $filteredModality->slug : null,
        ]);
    }

    public function copy(CopyWeekRequest $request, CopyWorkoutWeek $copy): RedirectResponse
    {
        $count = $copy->handle($request->user(), $request->validated());

        return to_route('schedule', ['date' => $request->validated('target_date'), 'modality' => $request->validated('modality')])->with('success', "$count treino(s) copiado(s) como rascunho. Os treinos existentes foram preservados.");
    }
}
