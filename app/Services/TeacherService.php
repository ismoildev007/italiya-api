<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    /** O'qituvchi profiliga tegishli (users jadvalidan tashqari) maydonlar. */
    private const USER_FIELDS = ['firstname', 'lastname', 'middlename', 'phone', 'email'];

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Teacher::query()
            ->with(['user', 'supervisedClasses'])
            ->search(
                $filters['search'] ?? null,
                ['user.firstname', 'user.lastname', 'user.middlename', 'user.phone']
            )
            ->filter($filters, [
                'specialization' => 'specialization',
                'salary_type' => 'salary_type',
                'is_active' => ['is_active', 'bool'],
            ])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Teacher
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                ...array_intersect_key($data, array_flip([...self::USER_FIELDS, 'username', 'password'])),
                'status' => 'active',
                'locale' => config('school.locale'),
            ]);

            $user->roles()->sync(array_filter([
                'teacher',
                ($data['is_class_teacher'] ?? false) ? 'class_teacher' : null,
            ]));

            return Teacher::create([
                ...$this->profileFields($data),
                'user_id' => $user->id,
                'hired_at' => $data['hired_at'] ?? now()->toDateString(),
                'fixed_salary' => $data['fixed_salary'] ?? 0,
                'per_lesson_rate' => $data['per_lesson_rate'] ?? 0,
                'is_active' => true,
            ]);
        });
    }

    public function update(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data) {
            if ($userFields = array_intersect_key($data, array_flip(self::USER_FIELDS))) {
                $teacher->user?->update($userFields);
            }

            $teacher->update($this->profileFields($data));

            return $teacher->fresh('user');
        });
    }

    public function delete(Teacher $teacher): void
    {
        abort_if(
            $teacher->supervisedClasses()->exists(),
            422,
            'Bu o\'qituvchi sinf rahbari — avval sinfga boshqa rahbar tayinlang.'
        );

        DB::transaction(function () use ($teacher) {
            $teacher->delete();
            $teacher->user?->update(['status' => 'blocked']);
        });
    }

    /**
     * Ish haqi rejimi: `fixed` — oylik maosh, `per_lesson` — bir dars narxi.
     * Tanlanmagan rejimning stavkasi nolga tushiriladi.
     */
    public function updateSalarySettings(Teacher $teacher, array $data): Teacher
    {
        $teacher->update([
            'salary_type' => $data['salary_type'],
            'fixed_salary' => $data['salary_type'] === Teacher::SALARY_FIXED ? $data['fixed_salary'] : 0,
            'per_lesson_rate' => $data['salary_type'] === Teacher::SALARY_PER_LESSON ? $data['per_lesson_rate'] : 0,
        ]);

        return $teacher->fresh('user');
    }

    private function profileFields(array $data): array
    {
        return array_diff_key($data, array_flip([
            ...self::USER_FIELDS, 'username', 'password', 'is_class_teacher',
        ]));
    }
}
