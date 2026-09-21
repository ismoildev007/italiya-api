<?php

namespace Database\Seeders;

use App\Models\CourseUnit;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Meeting;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TestTemplate;
use Illuminate\Database\Seeder;

/**
 * Uy ishlari (topshiriqlar va baholar) hamda Zoom mitinglar.
 */
class HomeworkSeeder extends Seeder
{
    public function run(): void
    {
        $homeworks = $submissions = 0;

        foreach (SchoolClass::get() as $ci => $class) {
            $unit = CourseUnit::where('level', $class->level)->where('skill', 'writing')->first();
            $test = TestTemplate::where('level', $class->level)->where('kind', 'homework')->first();

            foreach ([
                ["Insho: {$class->level} mavzusi bo'yicha", 'Darslikdagi namunaga qarab insho yozing va faylni yuklang.', $unit?->id, null, 3],
                ["Yozish va gapirish testi ({$class->level})", 'Platformadagi testni bajaring — AI baholaydi, o\'qituvchi tasdiqlaydi.', null, $test?->id, 5],
            ] as $hi => [$title, $desc, $unitId, $testId, $dueDays]) {
                $homework = Homework::create([
                    'school_id' => config('school.id'),
                    'teacher_id' => $class->class_teacher_id,
                    'class_id' => $class->id,
                    'level' => $class->level,
                    'title' => $title,
                    'description' => $desc,
                    'course_unit_id' => $unitId,
                    'test_template_id' => $testId,
                    'due_at' => now()->addDays($dueDays),
                    'max_score' => 100,
                    'status' => 'active',
                ]);
                $homeworks++;

                foreach (Student::where('class_id', $class->id)->limit(8)->get() as $si => $student) {
                    if ($si % 3 === 2) {
                        continue;
                    }

                    $checked = $si % 2 === 0;
                    HomeworkSubmission::create([
                        'school_id' => config('school.id'),
                        'homework_id' => $homework->id,
                        'student_id' => $student->id,
                        'text' => $hi === 0 ? 'Mi chiamo '.$student->firstname.'. Ecco il mio compito…' : null,
                        'file_path' => $hi === 0 ? "homeworks/{$homework->id}/submissions/{$student->id}.pdf" : null,
                        'submitted_at' => now()->subDays(2)->addHours($si),
                        'status' => $checked ? HomeworkSubmission::STATUS_CHECKED : HomeworkSubmission::STATUS_PENDING,
                        'score' => $checked ? 60 + ($si * 7 + $ci) % 40 : null,
                        'feedback' => $checked ? 'Yaxshi. Artikllarga e\'tibor bering.' : null,
                        'graded_by' => $checked ? 1 : null,
                        'graded_at' => $checked ? now()->subDay() : null,
                    ]);
                    $submissions++;
                }
            }
        }

        // Zoom mitinglar (mockupdagi 2 ta + har guruhga bittadan)
        $meetings = 0;
        $classes = SchoolClass::get();
        foreach ([
            ['Italiya Tili Grammatikasi va Muloqot', ['A1', 'A2'], [], now()->addDay()->setTime(19, 0)],
            ['Tibbiy Terminlar va Bemor Suhbatlari', ['C1'], ['medical'], now()->addDays(2)->setTime(20, 0)],
            ['Viza suhbatiga tayyorgarlik (DSU va grantlar)', [], ['special'], now()->addDays(3)->setTime(18, 30)],
        ] as $i => [$title, $levels, $tracks, $at]) {
            $meeting = Meeting::create([
                'school_id' => config('school.id'),
                'teacher_id' => $classes->first()->class_teacher_id,
                'title' => $title,
                'platform' => 'zoom',
                'link' => 'https://zoom.us/j/'.(81000000000 + $i * 777),
                'starts_at' => $at,
                'duration_minutes' => 90,
                'levels' => $levels ?: null,
                'tracks' => $tracks ?: null,
                'status' => 'scheduled',
            ]);
            $meeting->groups()->sync($classes->filter(fn ($c) => in_array($c->level, $levels, true) || in_array($c->track, $tracks, true))->pluck('id'));
            $meetings++;
        }

        $this->command?->info("✅ {$homeworks} ta uy ishi, {$submissions} ta topshiriq, {$meetings} ta Zoom miting yaratildi.");
    }
}
