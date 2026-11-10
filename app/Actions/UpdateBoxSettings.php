<?php

namespace App\Actions;

use App\Models\Box;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateBoxSettings
{
    public function handle(User $actor, array $data, ?UploadedFile $logo): void
    {
        if ($actor->role !== 'admin' || $actor->box_id === null) {
            throw new AuthorizationException;
        }
        $newPath = null;
        $oldPath = null;
        try {
            if ($logo) {
                $newPath = $logo->storePublicly('boxes/'.$actor->box_id, 'public');
                if (! $newPath) {
                    throw new RuntimeException('Não foi possível armazenar a logo. Tente novamente.');
                }
            }
            DB::transaction(function () use ($actor, $data, $newPath, &$oldPath): void {
                $box = Box::whereKey($actor->box_id)->lockForUpdate()->firstOrFail();
                $box->fill(array_intersect_key($data, array_flip(['name', 'head_coach_name', 'timezone', 'primary_color', 'secondary_color'])));
                if ($newPath || ($data['remove_logo'] ?? false)) {
                    $oldPath = $box->logo_path;
                    $box->logo_path = $newPath;
                }
                $box->save();
                $box->modalities()->sync($data['modalities']);
                $administrator = User::whereKey($actor->id)->where('box_id', $box->id)->lockForUpdate()->firstOrFail();
                $administrator->update(['name' => $data['administrator_name']]);
            }, 3);
        } catch (Throwable $error) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $error;
        }
        if ($oldPath && str_starts_with($oldPath, 'boxes/'.$actor->box_id.'/') && ! str_contains($oldPath, '..')) {
            Storage::disk('public')->delete($oldPath);
        }
        $actor->refresh();
    }
}
