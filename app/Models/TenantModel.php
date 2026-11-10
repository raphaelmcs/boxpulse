<?php

namespace App\Models;

use App\Models\Scopes\BoxScope;
use App\Tenancy\CurrentBox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new BoxScope);

        static::creating(function (TenantModel $model): void {
            $boxId = app(CurrentBox::class)->requireId();
            if ($model->box_id !== null && (int) $model->box_id !== $boxId) {
                throw new AuthorizationException('Box inválida.');
            }
            $model->box_id = $boxId;
        });

        static::updating(fn (TenantModel $model) => $model->authorizeTenantWrite());
        static::deleting(fn (TenantModel $model) => $model->authorizeTenantWrite());
    }

    private function authorizeTenantWrite(): void
    {
        $boxId = app(CurrentBox::class)->requireId();
        if ((int) $this->getRawOriginal('box_id') !== $boxId || (int) $this->box_id !== $boxId) {
            throw new AuthorizationException('O recurso não pertence à box atual.');
        }
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
