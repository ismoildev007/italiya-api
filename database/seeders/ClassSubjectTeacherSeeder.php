<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

/**
 * Har bir guruhga asosiy fan (yo'nalishiga qarab) va o'qituvchi biriktiriladi.
 */
class ClassSubjectTeacherSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = Subject::all()->keyBy('code');
        $teachers = Teacher::all();
        $bySpecialization = $teachers->groupBy('specialization');
        $created = 0;

        foreach (SchoolClass::orderBy('id')->get() as $index => $class) {
            $code = match ($class->track) {
                'medical' => 'ITA-MED',
                'farming' => 'ITA-AGR',
                'special' => 'ITA-SPC',
                default => 'ITA',
            };

            $subject = $subjects->get($code) ?? $subjects->first();
            $pool = $bySpecialization->get($subject->name) ?? $teachers;
            $teacher = $class->class_teacher_id ? Teacher::find($class->class_teacher_id) : $pool[$index % $pool->count()];

            ClassSubjectTeacher::updateOrCreate(
                ['class_id' => $class->id, 'subject_id' => $subject->id],
                ['school_id' => config('school.id'), 'teacher_id' => $teacher->id, 'hours_per_week' => count($class->schedule_days ?? [1, 3, 5]) * 2]
            );
            $created++;

            // Maxsus guruh qo'shimcha ingliz tili ham o'qiydi
            if ($class->track === 'special' && $subjects->has('ENG')) {
                $eng = $bySpecialization->get('Ingliz tili')?->first() ?? $teacher;
                ClassSubjectTeacher::updateOrCreate(
                    ['class_id' => $class->id, 'subject_id' => $subjects['ENG']->id],
                    ['school_id' => config('school.id'), 'teacher_id' => $eng->id, 'hours_per_week' => 2]
                );
                $created++;
            }
        }

        $this->command?->info("✅ {$created} ta guruh–fan–o'qituvchi biriktiruvi yaratildi.");
    }
}
