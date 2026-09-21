<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * created_by / updated_by / deleted_by ustunlarini avtomatik to'ldiradi.
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check()) {
                if ($model->isFillable('created_by') || in_array('created_by', $model->getFillable(), true)) {
                    $model->created_by ??= auth()->id();
                }
            }
        });

        static::updating(function (Model $model) {
            if (auth()->check() && in_array('updated_by', $model->getFillable(), true)) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (Model $model) {
            if (auth()->check() && in_array('deleted_by', $model->getFillable(), true)) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }
}
