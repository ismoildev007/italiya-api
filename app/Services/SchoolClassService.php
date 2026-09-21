<?php

namespace App\Services;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SchoolClassService
{
    public function __construct(
        private readonly AcademicYearService $years,
    ) {}

    /** @return Collection<int, SchoolClass> */
    public function list(array $filters = []): Collection
    {
        return SchoolClass::query()
            ->with(['academicYear', 'classTeacher.user'])
            ->withCount('students')
            ->search($filters['search'] ?? null, ['name'])
            ->filter($filters, [
                'academic_year_id' => 'academic_year_id',
                'grade' => 'grade',
                'level' => 'level',
                'track' => 'track',
                'shift' => 'shift',
                'class_teacher_id' => 'class_teacher_id',
                'is_active' => ['is_active', 'bool'],
            ])
            ->orderBy('grade')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): SchoolClass
    {
        $data['academic_year_id'] ??= $this->years->currentId();

        abort_unless($data['academic_year_id'], 422, 'Faol o\'quv yili topilmadi.');

        // Guruh darajasi (A1..C2) → `grade` ordinal sifatida saqlanadi (eski filtrlar uchun)
        if (! empty($data['level'])) {
            $data['grade'] = Level::ordinal($data['level']);
        }

        $data['grade'] ??= 1;
        $data['letter'] ??= (string) Str::of($data['name'])->afterLast('-')->limit(4, '');
        $data['shift'] ??= 1;

        return SchoolClass::create($data)->load('classTeacher.user');
    }

    public function update(SchoolClass $class, array $data): SchoolClass
    {
        if (! empty($data['level'])) {
            $data['grade'] = Level::ordinal($data['level']);
        }

        $class->update($data);

        return $class->fresh('classTeacher.user');
    }

    public function delete(SchoolClass $class): void
    {
        abort_if(
            $class->students()->exists(),
            422,
            'Sinfda o\'quvchilar bor — avval ularni boshqa sinfga o\'tkazing.'
        );

        $class->delete();
    }

    public function withDetails(SchoolClass $class): SchoolClass
    {
        return $class->load([
            'academicYear',
            'classTeacher.user',
            'subjectTeachers.subject',
            'subjectTeachers.teacher.user',
        ])->loadCount('students');
    }

    /** @return Collection<int, Student> */
    public function students(SchoolClass $class): Collection
    {
        return $class->students()
            ->with(['balance', 'activeTariff.tariff'])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();
    }

    /**
     * Sinfga fan o'qituvchilarini biriktiradi (fan bo'yicha almashtiriladi).
     *
     * @param  array<int, array{subject_id:int, teacher_id:int, hours_per_week?:int}>  $assignments
     * @return Collection<int, ClassSubjectTeacher>
     */
    public function assignTeachers(SchoolClass $class, array $assignments): Collection
    {
        foreach ($assignments as $assignment) {
            ClassSubjectTeacher::updateOrCreate(
                ['class_id' => $class->id, 'subject_id' => $assignment['subject_id']],
                [
                    'teacher_id' => $assignment['teacher_id'],
                    'hours_per_week' => $assignment['hours_per_week'] ?? 2,
                ]
            );
        }

        return $class->subjectTeachers()->with(['subject', 'teacher.user'])->get();
    }

    /** Sinf rahbari — har bir sinfga bitta. */
    public function assignClassTeacher(SchoolClass $class, int $teacherId): SchoolClass
    {
        $class->update(['class_teacher_id' => $teacherId]);

        return $class->fresh('classTeacher.user');
    }
}
