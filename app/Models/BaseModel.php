<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Domen modellari uchun umumiy asos: `school_id` bilan bog'liq mantiq
 * va factory qo'llab-quvvatlashi.
 */
abstract class BaseModel extends Model
{
    use BelongsToSchool, Filterable, HasFactory;
}
