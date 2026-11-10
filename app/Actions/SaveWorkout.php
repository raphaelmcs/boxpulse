<?php

namespace App\Actions;

use App\Models\TrainingModality;
use App\Models\User;
use App\Models\Workout;
use App\Workouts\BoxModalities;
use App\Workouts\SuggestedLoads;
use App\Workouts\TimerConfiguration;
use App\Workouts\WorkoutDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveWorkout
{
    public function __construct(private WorkoutDefinition $definition) {}

    public function handle(User $actor, array $input, ?Workout $workout = null): Workout
    {
        Gate::forUser($actor)->authorize($workout ? 'update' : 'create', $workout ?? Workout::class);
        $data = $this->definition->validate($input);

        return DB::transaction(function () use ($actor, $data, $workout): Workout {
            $box = BoxModalities::lock($actor);
            $existing = $workout ? Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail() : null;
            if (! $existing || $data['status'] === 'published' || (int) $data['training_modality_id'] !== $existing->training_modality_id) {
                BoxModalities::ensureEnabled($box, (int) $data['training_modality_id']);
            }
            if ($workout) {
                $workout = $existing;
                $workout->content_version++;
                $loads = $workout->blocks->keyBy('type');
                foreach ($data['blocks'] ?? [] as $index => $block) {
                    if (! array_key_exists('suggested_loads', $block)) {
                        $data['blocks'][$index]['suggested_loads'] = $loads->get($block['type'])?->suggested_loads;
                    }
                }
                app(DeleteWorkout::class)->deleteBlocks($workout);
            } else {
                $workout = new Workout;
                $workout->created_by = $actor->id;
            }
            $workout->fill(array_intersect_key($data, array_flip(['training_modality_id', 'structure', 'title', 'scheduled_for', 'status'])));
            $workout->title = $data['title'] ?? 'Treino de '.TrainingModality::findOrFail($data['training_modality_id'])->name;
            $workout->save();
            foreach ($data['blocks'] ?? [] as $position => $block) {
                $stations = $block['stations'] ?? [];
                unset($block['stations']);
                $block['suggested_loads'] = SuggestedLoads::normalize($block['suggested_loads'] ?? null);
                $block['position'] = $position;
                $block['timer_settings'] = TimerConfiguration::normalize($block['timer_type'], $block['timer_settings'] ?? []);
                $created = $workout->blocks()->create($block);
                foreach ($stations as $stationPosition => $station) {
                    $created->stations()->create([...$station, 'position' => $stationPosition]);
                }
            }

            return $workout->load('modality', 'blocks.stations');
        }, 3);
    }
}
