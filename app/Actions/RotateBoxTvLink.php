<?php

namespace App\Actions;

use App\Models\User;
use App\Workouts\BoxModalities;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RotateBoxTvLink
{
    public function handle(User $actor, bool $confirmed): void
    {
        abort_unless($actor->role === 'admin', 403);

        DB::transaction(function () use ($actor, $confirmed): void {
            $box = BoxModalities::lock($actor);
            if ($box->tv_access_hash && ! $confirmed) {
                throw ValidationException::withMessages(['confirmation' => 'Confirme a substituição do link da TV.']);
            }

            $token = bin2hex(random_bytes(32));
            $box->tv_access_hash = hash('sha256', $token);
            $box->tv_access_token = $token;
            $box->save();
        }, 3);
    }
}
