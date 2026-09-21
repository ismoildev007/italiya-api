<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Lesson;
use Database\Seeders\Data\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * O'tkazilgan darslar — soatbay ish haqi hisobining asosi.
 */
class LessonSeeder extends Seeder
{
    private const TOPICS = [
        'Grammatica: presente indicativo', 'Lessico: la famiglia e gli amici', 'Ascolto: dialogo al bar',
        'Lettura: un articolo di giornale', 'Scrittura: una e-mail formale', 'Parlato: presentarsi',
        'Ripasso e correzione dei compiti', 'Cultura italiana', 'Simulazione d\'esame (mock)',
    ];

    public function run(): void
    {
        $year = AcademicYear::active()->firstOrFail();
        $days = SchoolCalendar::schoolDays($year->starts_on->copy(), now());

        if (empty($days)) {
            return;
        }

        $assignments = ClassSubjectTeacher::with('schoolClass')->get();
        $rows = [];

        foreach ($days as $dayIndex => $day) {
            foreach ($assignments as $assignment) {
                // Haftalik soatga qarab: haftada 4 soat ≈ har 1.5 kunda bir dars
                $frequency = max(1, (int) round(6 / max(1, $assignment->hours_per_week)));

                if (($dayIndex + $assignment->id) % $frequency !== 0) {
                    continue;
                }

                $slot = ($assignment->id % 6) + 1;
                $shift = $assignment->schoolClass?->shift ?? 1;
                $startHour = $shift === 1 ? 8 : 13;

                $rows[] = [
                    'school_id' => config('school.id'),
                    'academic_year_id' => $year->id,
                    'class_id' => $assignment->class_id,
                    'subject_id' => $assignment->subject_id,
                    'teacher_id' => $assignment->teacher_id,
                    'date' => $day->toDateString(),
                    'start_time' => sprintf('%02d:%02d:00', $startHour + intdiv($slot * 45, 60), ($slot * 45) % 60),
                    'end_time' => sprintf('%02d:%02d:00', $startHour + intdiv($slot * 45 + 45, 60), ($slot * 45 + 45) % 60),
                    'topic' => self::TOPICS[($dayIndex + $assignment->id) % count(self::TOPICS)],
                    'duration_hours' => 1,
                    'status' => Lesson::STATUS_CONDUCTED,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('lessons')->insert($chunk);
        }

        $this->command?->info('✅ '.count($rows)." ta o'tkazilgan dars yozuvi yaratildi.");
    }
}
