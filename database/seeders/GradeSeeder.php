<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kundalik baholar (5 ballik tizim). Har bir baho tarixi saqlanadi —
 * fan/sinf/davr kesimida statistika chiqarish uchun yetarli hajm.
 */
class GradeSeeder extends Seeder
{
    private const COMMENTS = [
        null, null, null,
        'Faol qatnashdi', 'Uy vazifasi bajarilmagan', 'Mavzuni yaxshi o\'zlashtirgan',
        'Qo\'shimcha mashq talab etiladi', 'Doskada javob berdi',
    ];

    public function run(): void
    {
        mt_srand(909);

        $studentsByClass = Student::orderBy('id')->get()->groupBy('class_id');
        $lessons = Lesson::orderBy('date')->get();

        if ($lessons->isEmpty()) {
            $this->command?->warn('Darslar yo\'q — baholar yaratilmadi.');

            return;
        }

        $rows = [];
        $distribution = [];

        foreach ($lessons as $lessonIndex => $lesson) {
            $students = $studentsByClass->get($lesson->class_id, collect());

            foreach ($students as $index => $student) {
                // Har darsda sinfning ~35% i baholanadi
                if (($index + $lessonIndex) % 3 !== 0) {
                    continue;
                }

                $roll = ($index * 17 + $lessonIndex * 31) % 100;

                $value = match (true) {
                    $roll < 30 => 5,
                    $roll < 65 => 4,
                    $roll < 90 => 3,
                    default => 2,
                };

                $distribution[$value] = ($distribution[$value] ?? 0) + 1;

                $rows[] = [
                    'school_id' => config('school.id'),
                    'student_id' => $student->id,
                    'subject_id' => $lesson->subject_id,
                    'class_id' => $lesson->class_id,
                    'teacher_id' => $lesson->teacher_id,
                    'value' => $value,
                    'max_value' => 5,
                    'type' => ($lessonIndex % 7 === 0) ? 'homework' : 'daily',
                    'source_type' => null,
                    'source_id' => null,
                    'date' => $lesson->date->toDateString(),
                    'comment' => self::COMMENTS[($index + $lessonIndex) % count(self::COMMENTS)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('grades')->insert($chunk);
        }

        ksort($distribution);
        $summary = collect($distribution)->map(fn ($count, $value) => "{$value}: {$count}")->implode(', ');

        $this->command?->info('✅ '.count($rows).' ta baho yaratildi.');
        $this->command?->line("   • Taqsimot — {$summary}");
    }
}
