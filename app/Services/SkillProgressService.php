<?php

namespace App\Services;

use App\Models\SkillProgress;
use App\Models\Student;
use App\Models\TestAttempt;

/**
 * Ko'nikmalar bo'yicha progress: har bir topshirilgan test/mashq natijasi
 * daraja × ko'nikma kesimida yig'iladi (mockupdagi skill barlar).
 */
class SkillProgressService
{
    /** Urinish natijasidan bo'limlar bo'yicha ko'nikma progressini yangilaydi. */
    public function applyAttempt(TestAttempt $attempt): void
    {
        $attempt->loadMissing(['answers.question', 'assignment.template', 'student']);

        $template = $attempt->assignment?->template;
        $level = $template?->level ?: $attempt->student?->effectiveLevel();

        if (! $template || ! $level) {
            return;
        }

        $bySkill = $attempt->answers->groupBy(fn ($answer) => $answer->question?->skill ?: $template->skill ?: 'grammar');

        foreach ($bySkill as $skill => $answers) {
            $max = (float) $answers->sum(fn ($a) => (float) $a->question?->score);
            $earned = (float) $answers->sum(fn ($a) => (float) $a->score);
            $percent = $max > 0 ? round($earned / $max * 100, 2) : 0;

            $this->record($attempt->student, $level, (string) $skill, $percent, (int) round($earned * 10));
        }
    }

    /** Bitta natijani yozadi — foiz o'rtacha, ballar yig'iladi. */
    public function record(Student $student, string $level, string $skill, float $percent, int $points = 0): SkillProgress
    {
        $progress = SkillProgress::firstOrCreate(
            ['student_id' => $student->id, 'level' => $level, 'skill' => $skill],
            ['school_id' => config('school.id'), 'percent' => 0, 'points' => 0, 'attempts_count' => 0],
        );

        $count = $progress->attempts_count;

        // Oxirgi natijalar ko'proq ta'sir qiladi (eksponensial silliqlash)
        $newPercent = $count === 0 ? $percent : round($progress->percent * 0.6 + $percent * 0.4, 2);

        $progress->update([
            'percent' => max(0, min(100, $newPercent)),
            'points' => $progress->points + max(0, $points),
            'attempts_count' => $count + 1,
            'last_activity_at' => now(),
        ]);

        $student->forceFill(['last_activity_at' => now()])->saveQuietly();

        return $progress->fresh();
    }

    /**
     * Daraja bo'yicha to'liq ko'rinish (barcha ko'nikmalar, bo'lmasa 0).
     *
     * @return array<string, array{percent: float, points: int, attempts: int}>
     */
    public function forLevel(Student $student, string $level): array
    {
        $rows = SkillProgress::where('student_id', $student->id)->where('level', $level)->get()->keyBy('skill');

        $result = [];

        foreach (array_keys(config('academy.skills')) as $skill) {
            $row = $rows->get($skill);
            $result[$skill] = [
                'label' => config("academy.skills.{$skill}"),
                'percent' => (float) ($row?->percent ?? 0),
                'points' => (int) ($row?->points ?? 0),
                'attempts' => (int) ($row?->attempts_count ?? 0),
            ];
        }

        return $result;
    }

    /** Asosiy 4 ko'nikma o'rtachasi — darajani "o'tgan" deb hisoblash uchun. */
    public function overall(Student $student, string $level): float
    {
        $skills = $this->forLevel($student, $level);
        $core = collect(config('academy.core_skills'))->map(fn ($s) => $skills[$s]['percent'] ?? 0);

        return round((float) $core->avg(), 2);
    }
}
