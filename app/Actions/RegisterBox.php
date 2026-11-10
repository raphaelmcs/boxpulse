<?php

namespace App\Actions;

use App\Models\Box;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RegisterBox
{
    public function handle(array $data, ?UploadedFile $logo = null): User
    {
        $newPath = null;
        try {
            return DB::transaction(function () use ($data, $logo, &$newPath): User {
                $box = Box::create([
                    'name' => $data['box_name'], 'head_coach_name' => $data['head_coach_name'],
                    'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                    'primary_color' => $data['primary_color'] ?? '#C8FA3C', 'secondary_color' => $data['secondary_color'] ?? '#38BDF8',
                ]);
                $box->modalities()->sync($data['modalities']);
                $user = new User(['name' => $data['name'], 'username' => $data['username'], 'password' => $data['password']]);
                $user->box()->associate($box);
                $user->role = 'admin';
                $user->save();
                if ($logo) {
                    $newPath = $logo->storePublicly('boxes/'.$box->id, 'public');
                    if (! $newPath) {
                        throw new RuntimeException('Não foi possível armazenar a logo. Tente novamente.');
                    }
                    $box->update(['logo_path' => $newPath]);
                }

                return $user;
            });
        } catch (Throwable $error) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $error;
        }
    }
}
