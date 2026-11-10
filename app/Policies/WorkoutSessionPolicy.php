<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutSession;

class WorkoutSessionPolicy
{
    public function view(User $user, WorkoutSession $session): bool
    {
        return $user->box_id !== null && (int) $user->box_id === (int) $session->box_id && in_array($user->role, ['admin', 'coach'], true);
    }

    public function control(User $user, WorkoutSession $session): bool
    {
        return $this->view($user, $session) && ($user->role === 'admin' || (int) $session->responsible_user_id === $user->id);
    }
}
