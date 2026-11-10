<?php

namespace App\Http\Controllers;

use App\Actions\DeleteWorkout;
use App\Actions\SaveWorkout;
use App\Http\Requests\WorkoutRequest;
use App\Models\TrainingModality;
use App\Models\Workout;
use App\Workouts\WorkoutDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkoutController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Workout::class);
        $filter = $request->validate(['modality' => ['nullable', 'exists:training_modalities,slug']]);
        $today = now($request->user()->box->timezone)->toDateString();
        $query = Workout::with('modality')->where('scheduled_for', '<', $today)->orderByDesc('scheduled_for')->orderByDesc('id');
        if (! empty($filter['modality'])) {
            $query->whereHas('modality', fn ($q) => $q->where('slug', $filter['modality']));
        }

        return view('workouts.history', ['box' => $request->user()->box, 'workouts' => $query->paginate(20)->withQueryString(), 'modalities' => TrainingModality::all(), 'filter' => $filter['modality'] ?? '']);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Workout::class);
        $data = $request->validate(['modality' => ['nullable', 'exists:training_modalities,slug'], 'structure' => ['nullable', 'in:full,main'], 'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31']]);
        $modalities = $request->user()->box->modalities()->orderBy('training_modalities.id');
        $modality = isset($data['modality']) ? $modalities->where('slug', $data['modality'])->firstOrFail() : $modalities->firstOrFail();
        $form = ['training_modality_id' => $modality->id, 'structure' => $data['structure'] ?? 'main', 'scheduled_for' => $data['date'] ?? now($request->user()->box->timezone)->toDateString(), 'status' => 'draft'];

        return view('workouts.form', ['box' => $request->user()->box, 'workout' => null, 'form' => $form, 'modality' => $modality]);
    }

    public function store(WorkoutRequest $request, SaveWorkout $save): RedirectResponse
    {
        $workout = $save->handle($request->user(), $request->workoutData());

        return to_route($request->boolean('preview_after_save') ? 'workouts.preview' : 'workouts.show', $workout)->with('success', 'Treino salvo com sucesso.');
    }

    public function edit(Request $request, Workout $workout, WorkoutDefinition $definition): View
    {
        Gate::authorize('update', $workout);
        $workout->load('blocks.stations', 'modality');

        return view('workouts.form', ['box' => $request->user()->box, 'workout' => $workout, 'form' => $definition->fromWorkout($workout), 'modality' => $workout->modality]);
    }

    public function update(WorkoutRequest $request, Workout $workout, SaveWorkout $save): RedirectResponse
    {
        $updated = $save->handle($request->user(), $request->workoutData(), $workout);

        return to_route($request->boolean('preview_after_save') ? 'workouts.preview' : 'workouts.show', $updated)->with('success', 'Treino atualizado com sucesso.');
    }

    public function show(Request $request, Workout $workout): View
    {
        Gate::authorize('view', $workout);

        return view('workouts.show', ['box' => $request->user()->box, 'workout' => $workout->load('modality', 'blocks.stations')]);
    }

    public function destroy(Request $request, Workout $workout, DeleteWorkout $delete): RedirectResponse
    {
        Gate::authorize('delete', $workout);
        $request->validate(['confirmation' => ['accepted']]);
        $date = $workout->scheduled_for->toDateString();
        $delete->handle($request->user(), $workout);

        return to_route('schedule', ['date' => $date])->with('success', 'Treino excluído.');
    }
}
