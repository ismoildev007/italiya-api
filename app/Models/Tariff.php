<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tariff extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['school_id', 'name', 'monthly_price', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function studentTariffs(): HasMany
    {
        return $this->hasMany(StudentTariff::class);
    }
}
