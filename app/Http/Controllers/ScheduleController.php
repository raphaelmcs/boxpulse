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
        $filter = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-08', 'before_or_equal:2100-12-24'], 'modality' => ['nullable', 'exists:training_modalities,slug']]);
        $box = $request->user()->box;
        $today = CarbonImmutable::now($box->timezone);
        $selected = CarbonImmutable::parse($filter['date'] ?? $today->toDateString(), $box->timezone);
        $start = $selected->startOfWeek(CarbonImmutable::MONDAY);
        $query = Workout::with('modality')->whereBetween('scheduled_for', [$start->toDateString(), $start->addDays(6)->toDateString()])->orderBy('training_modality_id')->orderBy('id');
        if (! empty($filter['modality'])) {
            $query->whereHas('modality', fn ($q) => $q->where('slug', $filter['modality']));
        }

        $activeIds = $box->modalities()->pluck('training_modalities.id')->all();

        return view('schedule.index', ['box' => $box, 'today' => $today, 'selected' => $selected, 'start' => $start, 'days' => collect(range(0, 6))->map(fn ($day) => $start->addDays($day)), 'workouts' => $query->get()->sortBy(fn ($workout) => ! in_array($workout->training_modality_id, $activeIds))->groupBy(fn ($workout) => $workout->scheduled_for->toDateString()), 'modalities' => TrainingModality::all()->sortBy(fn ($modality) => ! in_array($modality->id, $activeIds)), 'activeModalityIds' => $activeIds, 'filter' => $filter['modality'] ?? '']);
    }

    public function copy(CopyWeekRequest $request, CopyWorkoutWeek $copy): RedirectResponse
    {
        $count = $copy->handle($request->user(), $request->validated());

        return to_route('schedule', ['date' => $request->validated('target_date')])->with('success', "$count treino(s) copiado(s) como rascunho. Os treinos existentes foram preservados.");
    }
}
