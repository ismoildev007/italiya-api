<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Student;
use App\Services\StudentStatsService;
use App\Support\Level;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * O'tgan oy uchun bir nechta sertifikat (oyiga 1 marta qoidasi bilan).
 */
class CertificateSeeder extends Seeder
{
    public function run(StudentStatsService $stats): void
    {
        $period = now()->subMonth();
        $count = 0;

        foreach (Student::whereNotNull('level')->orderBy('id')->get()->filter(fn ($s) => $s->id % 6 === 1)->take(24) as $i => $student) {
            $overall = 62 + ($student->id * 7) % 35;
            $skills = collect(config('academy.core_skills'))->mapWithKeys(fn ($s, $k) => [$s => min(100, $overall - 8 + ($student->id * ($k + 3)) % 17)])->all();

            Certificate::create([
                'school_id' => config('school.id'),
                'student_id' => $student->id,
                'serial' => sprintf('ITA-%04d%02d-%04d', $period->year, $period->month, ++$count),
                'level' => $student->level,
                'track' => $student->track,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'title' => 'Certificato di Competenza Linguistica — '.Level::title($student->level),
                'attendance_score' => min(100, $overall + 5),
                'homework_score' => $overall - 3,
                'test_score' => $overall,
                'overall_score' => $overall,
                'skills' => $skills,
                'grade_label' => Level::gradeLabel($overall),
                'verify_code' => strtoupper(Str::random(10)),
                'issued_at' => $period->copy()->endOfMonth()->subDays(2),
            ]);
        }

        $this->command?->info("✅ {$count} ta sertifikat ({$period->format('Y-m')}) yaratildi.");
    }
}
