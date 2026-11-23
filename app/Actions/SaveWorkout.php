<?php

namespace App\Actions;

use App\Models\TrainingModality;
use App\Models\User;
use App\Models\Workout;
use App\Workouts\BoxModalities;
use App\Workouts\SuggestedLoads;
use App\Workouts\TimerConfiguration;
use App\Workouts\WorkoutDefinition;
use App\Workouts\WorkoutTextStyle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SaveWorkout
{
    public function __construct(private WorkoutDefinition $definition) {}

    /** @param array{version: int, write_id: string}|null $editor */
    public function handle(User $actor, array $input, ?Workout $workout = null, ?array $editor = null): Workout
    {
        Gate::forUser($actor)->authorize($workout ? 'update' : 'create', $workout ?? Workout::class);

        return DB::transaction(function () use ($actor, $input, $workout, $editor): Workout {
            $box = BoxModalities::lock($actor);
            $existing = $workout ? Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail() : null;
            if ($editor && ! $existing) {
                $existing = Workout::where('editor_create_id', $editor['write_id'])->lockForUpdate()->first();
                abort_if($existing && (int) $existing->created_by !== $actor->id, Response::HTTP_CONFLICT, 'Este identificador de gravação já está em uso.');
            }
            $hash = $editor ? hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)) : null;
            if ($editor && $existing) {
                if ($existing->editor_write_id === $editor['write_id']) {
                    abort_unless($existing->edit_version === $editor['version'] + 1 && $existing->editor_write_hash === $hash,
                        Response::HTTP_CONFLICT, 'Este identificador pertence a uma gravação diferente.');

                    return $existing;
                }
                self::checkVersion($existing, $editor['version']);
            }
            $data = $this->definition->validate($input, keepIncompleteStages: $editor !== null);
            $currentModality = $editor ? ($existing?->editor_draft['training_modality_id'] ?? $existing?->training_modality_id) : $existing?->training_modality_id;
            if (! $existing || $data['status'] === 'published' || (int) $data['training_modality_id'] !== (int) $currentModality) {
                BoxModalities::ensureEnabled($box, (int) $data['training_modality_id']);
            }

            $target = $existing ?? new Workout;
            if (! $existing) {
                $target->created_by = $actor->id;
                $target->editor_create_id = $editor['write_id'] ?? null;
            }
            $target->editor_write_id = $editor['write_id'] ?? null;
            $target->editor_write_hash = $hash;
            $data = $this->mergeEditorFields($target, $data);
            if ($editor && $target->status === 'published' && $data['status'] === 'draft') {
                $target->editor_draft = $data;
                $target->edit_version++;
                $target->save();

                return $target;
            }

            return $this->replaceContent($target, $data);
        }, 3);
    }

    public static function checkVersion(Workout $workout, ?int $expected): void
    {
        abort_if($expected !== null && $workout->edit_version !== $expected, Response::HTTP_CONFLICT, 'Outra aba ou pessoa alterou este treino. Suas alterações continuam nesta aba.');
    }

    public function changeStatus(User $actor, Workout $workout, string $status, ?int $expectedVersion): Workout
    {
        Gate::forUser($actor)->authorize('update', $workout);

        return DB::transaction(function () use ($actor, $workout, $status, $expectedVersion): Workout {
            $box = BoxModalities::lock($actor);
            $locked = Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail();
            self::checkVersion($locked, $expectedVersion);
            $data = [...$this->definition->forEditing($locked), 'status' => $status];
            if ($status === 'published') {
                BoxModalities::ensureEnabled($box, (int) $data['training_modality_id']);
                $data = $this->definition->validate($data);
            }
            $locked->editor_write_id = null;
            $locked->editor_write_hash = null;
            if ($locked->editor_draft !== null) {
                return $this->replaceContent($locked, $data);
            }
            if ($locked->status !== $status) {
                $locked->status = $status;
                $locked->edit_version++;
                $locked->save();
            }

            return $locked;
        }, 3);
    }

    private function mergeEditorFields(Workout $workout, array $data): array
    {
        $previous = $workout->exists ? $this->definition->forEditing($workout) : [];
        $byType = array_column($previous['blocks'] ?? [], null, 'type');
        foreach ($data['blocks'] as &$block) {
            $before = $byType[$block['type']] ?? [];
            $block += array_intersect_key($before, array_flip(['suggested_loads', 'load_entries']));
            $block['text_style'] = $this->textStyle($block, $before, 'title', ['description', 'notes']);
            foreach ($block['stations'] ?? [] as $index => $station) {
                $block['stations'][$index]['text_style'] = $this->textStyle($station, $before['stations'][$index] ?? [], 'name', ['description']);
            }
            $block['suggested_loads'] = SuggestedLoads::normalize($block['suggested_loads'] ?? null);
            $block['load_entries'] = SuggestedLoads::normalizeEntries($block['load_entries'] ?? []);
        }
        unset($block);
        $data['presentation_options'] ??= $previous['presentation_options'] ?? null;
        $data['title'] ??= 'Treino de '.TrainingModality::findOrFail($data['training_modality_id'])->name;

        return $data;
    }

    private function textStyle(array $input, array $before, string $titleField, array $lineFields): ?array
    {
        if (! array_key_exists('text_style', $input)) {
            foreach ([$titleField, ...$lineFields] as $field) {
                if (($input[$field] ?? null) === ($before[$field] ?? null) && isset($before['text_style'][$field])) {
                    $input['text_style'][$field] = $before['text_style'][$field];
                }
            }
        }

        return WorkoutTextStyle::normalize($input, $titleField, $lineFields);
    }

    private function replaceContent(Workout $workout, array $data): Workout
    {
        if ($workout->exists) {
            $workout->edit_version++;
            $workout->content_version++;
            app(DeleteWorkout::class)->deleteBlocks($workout);
        }
        $workout->fill(array_intersect_key($data, array_flip(['training_modality_id', 'structure', 'title', 'scheduled_for', 'status'])));
        $workout->presentation_options = $data['presentation_options'] ?? null;
        $blocks = $data['blocks'];
        if ($data['status'] === 'draft') {
            $blocks = array_values($this->definition->meaningfulBlocks($blocks));
        }
        $workout->editor_draft = count($blocks) < count($data['blocks']) ? $data : null;
        $workout->save();
        foreach ($blocks as $position => $block) {
            $stations = $block['stations'] ?? [];
            unset($block['stations']);
            $block['position'] = $position;
            $block['timer_settings'] = TimerConfiguration::normalize($block['timer_type'], $block['timer_settings'] ?? []);
            $created = $workout->blocks()->create($block);
            foreach ($stations as $stationPosition => $station) {
                $created->stations()->create([...$station, 'position' => $stationPosition]);
            }
        }

        return $workout->load('modality', 'blocks.stations');
    }
}
