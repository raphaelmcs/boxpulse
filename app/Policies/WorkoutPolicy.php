<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

class WorkoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function view(User $user, Workout $workout): bool
    {
        return $user->box_id !== null && (int) $user->box_id === (int) $workout->box_id && in_array($user->role, ['admin', 'coach'], true);
    }

    public function create(User $user): bool
    {
        return $user->box_id !== null && in_array($user->role, ['admin', 'coach'], true);
    }

    public function update(User $user, Workout $workout): bool
    {
        return $this->view($user, $workout);
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $this->view($user, $workout) && $user->role === 'admin';
    }
}
