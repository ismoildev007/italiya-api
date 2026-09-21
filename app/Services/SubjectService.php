<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;

class SubjectService
{
    /** @return Collection<int, Subject> */
    public function list(array $filters = []): Collection
    {
        return Subject::query()
            ->search($filters['search'] ?? null, ['name', 'code'])
            ->filter($filters, ['is_active' => ['is_active', 'bool']])
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Subject
    {
        return Subject::create($data);
    }

    public function update(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        return $subject->fresh();
    }

    public function delete(Subject $subject): void
    {
        $subject->delete();
    }
}
