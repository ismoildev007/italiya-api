<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Har bir maktab alohida bazaga deploy qilinadi, shuning uchun hozircha
 * `school_id` doim config('school.id') ga teng. Global scope kelajakda
 * bitta serverda bir nechta maktab bo'lganda ham kod o'zgarmasligi uchun
 * oldindan qo'yilgan.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $builder->where($model->getTable().'.school_id', config('school.id'));
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->school_id)) {
                $model->school_id = config('school.id');
            }
        });
    }

    public function scopeWithoutSchoolScope(Builder $query): Builder
    {
        return $query->withoutGlobalScopes();
    }
}
