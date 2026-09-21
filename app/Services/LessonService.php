<?php

namespace App\Services;

use App\Models\Lesson;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LessonService
{
    public function __construct(
        private readonly AcademicYearService $years,
    ) {}

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return Lesson::query()
            ->with(['schoolClass', 'subject', 'teacher.user'])
            ->filter($filters, [
                'class_id' => 'class_id',
                'teacher_id' => 'teacher_id',
                'subject_id' => 'subject_id',
                'status' => 'status',
                'from' => ['date', 'date_from'],
                'to' => ['date', 'date_to'],
            ])
            ->orderByDesc('date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Lesson
    {
        return Lesson::create([
            ...$data,
            'academic_year_id' => $this->years->currentId(),
            'status' => $data['status'] ?? Lesson::STATUS_CONDUCTED,
        ])->load(['schoolClass', 'subject', 'teacher.user']);
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        $lesson->update($data);

        return $lesson->fresh(['schoolClass', 'subject', 'teacher.user']);
    }

    public function delete(Lesson $lesson): void
    {
        $lesson->delete();
    }

    public function withDetails(Lesson $lesson): Lesson
    {
        return $lesson->load(['schoolClass', 'subject', 'teacher.user']);
    }
}
