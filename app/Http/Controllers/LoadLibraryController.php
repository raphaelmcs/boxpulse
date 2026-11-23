<?php

namespace App\Http\Controllers;

use App\Actions\SaveLoadMovement;
use App\Http\Requests\AnalyzeWorkoutRequest;
use App\Http\Requests\LoadMovementRequest;
use App\Http\Requests\LoadPresetRequest;
use App\Models\LoadMovement;
use App\Models\LoadPreset;
use App\Models\Workout;
use App\Workouts\LoadLibrary;
use App\Workouts\MovementCatalog;
use App\Workouts\WorkoutLoadAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoadLibraryController extends Controller
{
    public function index(Request $request, LoadLibrary $library, MovementCatalog $catalog): JsonResponse
    {
        Gate::authorize('create', Workout::class);

        return response()->json([
            'equipment' => LoadLibrary::EQUIPMENT,
            'presets' => $library->presets($request->user()->role === 'admin'),
            'movements' => $catalog->entries($request->user()->role === 'admin'),
            'contexts' => LoadLibrary::CONTEXTS,
        ]);
    }

    public function store(LoadPresetRequest $request, LoadLibrary $library): JsonResponse
    {
        $preset = LoadPreset::create($this->presetData($request));

        return response()->json($library->serialize($preset), 201);
    }

    public function update(LoadPresetRequest $request, LoadPreset $preset, LoadLibrary $library): JsonResponse
    {
        $preset->update($this->presetData($request, $preset));

        return response()->json($library->serialize($preset));
    }

    public function storeMovement(LoadMovementRequest $request, MovementCatalog $catalog, SaveLoadMovement $save): JsonResponse
    {
        return response()->json($catalog->serialize($save->handle($request->user(), new LoadMovement, $request->validated())), 201);
    }

    public function updateMovement(LoadMovementRequest $request, LoadMovement $movement, MovementCatalog $catalog, SaveLoadMovement $save): JsonResponse
    {
        return response()->json($catalog->serialize($save->handle($request->user(), $movement, $request->validated())));
    }

    public function analyze(AnalyzeWorkoutRequest $request, WorkoutLoadAnalyzer $analyzer, LoadLibrary $library, MovementCatalog $catalog): JsonResponse
    {
        return response()->json(['items' => $analyzer->analyze($request->validated(), $library->presets(), $catalog->entries())]);
    }

    private function presetData(LoadPresetRequest $request, ?LoadPreset $preset = null): array
    {
        $data = $request->validated();
        if (isset($data['movement_id'])) {
            $movement = LoadMovement::findOrFail($data['movement_id']);
            $data['equipment'] = $movement->equipment;
            $data['movement'] = $movement->name;
        }
        $data['name'] ??= $preset?->name ?? $data['movement'] ?? $data['equipment'];

        return $data;
    }
}
