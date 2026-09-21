<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLevelHistory extends BaseModel
{
    protected $table = 'student_level_history';

    protected $fillable = ['school_id', 'student_id', 'from_level', 'to_level', 'reason', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
