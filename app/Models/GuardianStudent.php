<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GuardianStudent extends Pivot
{
    protected $table = 'guardian_student';

    public $incrementing = true;

    protected $fillable = ['school_id', 'guardian_id', 'student_id', 'relation', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
