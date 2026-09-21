<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\Level;
use Illuminate\Database\Seeder;

/**
 * Guruhlar: daraja (A1..C2) × yo'nalish (umumiy / tibbiyot / fermer / maxsus).
 * Har bir guruhga bitta guruh rahbari (o'qituvchi) biriktiriladi.
 */
class SchoolClassSeeder extends Seeder
{
    /** [nom, daraja, yo'nalish, smena, kunlar, boshlanish, tugash] */
    public const GROUPS = [
        ['A1-Ertalab', 'A1', 'general', 1, [1, 3, 5], '09:00', '10:30'],
        ['A1-Kechki', 'A1', 'general', 2, [2, 4, 6], '18:00', '19:30'],
        ['A2-Ertalab', 'A2', 'general', 1, [1, 3, 5], '10:30', '12:00'],
        ['A2-Kechki', 'A2', 'general', 2, [2, 4, 6], '19:30', '21:00'],
        ['B1-Ertalab', 'B1', 'general', 1, [2, 4, 6], '09:00', '10:30'],
        ['B1-Kechki', 'B1', 'general', 2, [1, 3, 5], '18:00', '19:30'],
        ['B2-Kechki', 'B2', 'general', 2, [1, 3, 5], '19:30', '21:00'],
        ['C1-Kechki', 'C1', 'general', 2, [2, 4, 6], '18:00', '19:30'],
        ['C2-Shanba', 'C2', 'general', 1, [6], '10:00', '13:00'],
        ['Tibbiyot-B1', 'B1', 'medical', 2, [1, 3, 5], '18:00', '19:30'],
        ['Fermer-A2', 'A2', 'farming', 1, [2, 4], '11:00', '12:30'],
        ['Maxsus-B2', 'B2', 'special', 2, [2, 4, 6], '19:30', '21:00'],
    ];

    public function run(): void
    {
        $year = AcademicYear::active()->firstOrFail();
        $teachers = Teacher::with('user')->orderBy('id')->get();
        $bySpec = $teachers->groupBy('specialization');

        foreach (self::GROUPS as $index => [$name, $level, $track, $shift, $days, $start, $end]) {
            $pool = match ($track) {
                'medical' => $bySpec->get('Italyan tili — tibbiyot'),
                'farming' => $bySpec->get('Italyan tili — agrar'),
                'special' => $bySpec->get('Italyan tili — biznes va viza'),
                default => $bySpec->get('Italyan tili'),
            } ?? $teachers;

            $teacher = $pool[$index % $pool->count()] ?? null;

            SchoolClass::updateOrCreate(
                ['academic_year_id' => $year->id, 'name' => $name],
                [
                    'school_id' => config('school.id'),
                    'level' => $level,
                    'track' => $track,
                    'grade' => Level::ordinal($level),
                    'letter' => (string) ($index + 1),
                    'shift' => $shift,
                    'schedule_days' => $days,
                    'start_time' => $start,
                    'end_time' => $end,
                    'class_teacher_id' => $teacher?->id,
                    'room' => sprintf('%d-xona', ($index % 6) + 1),
                    'zoom_link' => 'https://zoom.us/j/'.(90000000000 + $index * 1111),
                    'starts_on' => $year->starts_on->toDateString(),
                    'capacity' => 15,
                    'is_active' => true,
                ]
            );

            $teacher?->user?->roles()->syncWithoutDetaching(['class_teacher']);
        }

        $this->command?->info('✅ '.count(self::GROUPS).' ta guruh yaratildi (A1 … C2, tibbiyot, fermer, maxsus).');
    }
}
