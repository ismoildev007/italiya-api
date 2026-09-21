<?php

namespace App\Services;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GradeService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Grade> */
    public function forStudent(int $studentId, array $filters = []): Collection
    {
        return $this->query(['student_id' => $studentId] + $filters)->get();
    }

    public function create(array $data): Grade
    {
        $grade = Grade::create($data);

        // Ota-onaga xabar hodisasi (Telegram bot uchun)
        $this->notifications->gradeCreated($grade->load(['student', 'subject']));

        return $grade;
    }

    /**
     * Sinf uchun to'plamli baholash.
     *
     * @return int Yaratilgan baholar soni
     */
    public function createBulk(array $data): int
    {
        $shared = [
            'subject_id' => $data['subject_id'],
            'class_id' => $data['class_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'type' => $data['type'],
            'date' => $data['date'],
            'max_value' => 5,
        ];

        $created = DB::transaction(fn () => collect($data['grades'])->map(
            fn (array $row) => Grade::create([...$shared, ...$row])
        ));

        foreach ($created as $grade) {
            $this->notifications->gradeCreated($grade->load(['student', 'subject']));
        }

        return $created->count();
    }

    public function update(Grade $grade, array $data): Grade
    {
        $grade->update($data);

        return $grade->fresh(['student', 'subject']);
    }

    public function delete(Grade $grade): void
    {
        $grade->delete();
    }

    public function withDetails(Grade $grade): Grade
    {
        return $grade->load(['student', 'subject', 'teacher.user', 'schoolClass']);
    }

    private function query(array $filters)
    {
        return Grade::query()
            ->with(['student', 'subject', 'teacher.user', 'schoolClass'])
            ->filter($filters, [
                'student_id' => 'student_id',
                'class_id' => 'class_id',
                'subject_id' => 'subject_id',
                'type' => 'type',
                'from' => ['date', 'date_from'],
                'to' => ['date', 'date_to'],
            ])
            ->orderByDesc('date');
    }
}
