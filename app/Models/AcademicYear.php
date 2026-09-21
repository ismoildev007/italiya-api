<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends BaseModel
{
    protected $fillable = ['school_id', 'name', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::active()->first();
    }
}
