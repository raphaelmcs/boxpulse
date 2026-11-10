<?php

namespace App\Models\Scopes;

use App\Tenancy\CurrentBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BoxScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $boxId = app(CurrentBox::class)->id();

        if ($boxId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('box_id'), $boxId);
    }
}
