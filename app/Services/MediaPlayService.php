<?php

namespace App\Services;

use App\Models\CourseUnit;
use App\Models\MediaPlay;
use App\Models\Student;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestSection;
use App\Models\UnitProgress;
use Illuminate\Database\Eloquent\Model;

/**
 * "Necha marta tinglash mumkin" qoidasi — darslik, test bo'limi va savol audiosi uchun.
 * Limit tugagach 423 (Locked) qaytadi va media boshqa ochilmaydi.
 */
class MediaPlayService
{
    /**
     * Eshitishni ro'yxatga oladi. Qaytaradi: {plays, limit, remaining, locked}.
     *
     * @return array{plays:int, limit:?int, remaining:?int, locked:bool}
     */
    public function register(Student $student, Model $playable, ?TestAttempt $attempt = null): array
    {
        $limit = $this->limitFor($playable, $attempt);

        $record = MediaPlay::firstOrCreate(
            [
                'student_id' => $student->id,
                'playable_type' => $playable::class,
                'playable_id' => $playable->getKey(),
                'test_attempt_id' => $attempt?->id,
            ],
            ['school_id' => config('school.id'), 'plays' => 0],
        );

        if ($limit !== null && $record->plays >= $limit) {
            abort(423, "Bu audioni tinglash limiti tugagan ({$limit} marta).");
        }

        $record->increment('plays');
        $record->update(['last_played_at' => now()]);

        if ($playable instanceof CourseUnit) {
            UnitProgress::updateOrCreate(
                ['course_unit_id' => $playable->id, 'student_id' => $student->id],
                ['school_id' => config('school.id')],
            )->increment('plays');
        }

        return $this->state($record->plays, $limit);
    }

    /** Joriy holat — hisoblamasdan. */
    public function status(Student $student, Model $playable, ?TestAttempt $attempt = null): array
    {
        $plays = (int) MediaPlay::where('student_id', $student->id)
            ->where('playable_type', $playable::class)
            ->where('playable_id', $playable->getKey())
            ->where('test_attempt_id', $attempt?->id)
            ->value('plays');

        return $this->state($plays, $this->limitFor($playable, $attempt));
    }

    public function limitFor(Model $playable, ?TestAttempt $attempt = null): ?int
    {
        return match (true) {
            $playable instanceof CourseUnit => $playable->playsLimit(),
            $playable instanceof TestSection => $playable->playsLimit(),
            $playable instanceof TestQuestion => $playable->max_plays
                ?? $playable->section?->playsLimit()
                ?? $playable->template?->playsLimit(),
            default => null,
        };
    }

    private function state(int $plays, ?int $limit): array
    {
        return [
            'plays' => $plays,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $plays),
            'locked' => $limit !== null && $plays >= $limit,
        ];
    }
}
