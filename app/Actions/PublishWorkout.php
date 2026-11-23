<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;

class PublishWorkout
{
    public function __construct(private SaveWorkout $saveWorkout) {}

    public function handle(User $actor, Workout $workout, string $status, ?int $expectedVersion = null): Workout
    {
        return $this->saveWorkout->changeStatus($actor, $workout, $status, $expectedVersion);
    }
}
