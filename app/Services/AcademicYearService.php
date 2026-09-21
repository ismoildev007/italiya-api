<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Collection;

class AcademicYearService
{
    /** @return Collection<int, AcademicYear> */
    public function list(): Collection
    {
        return AcademicYear::withCount('classes')->orderByDesc('starts_on')->get();
    }

    public function create(array $data): AcademicYear
    {
        $year = AcademicYear::create($data);

        if ($data['is_active'] ?? false) {
            $this->activate($year);
        }

        return $year->fresh();
    }

    public function update(AcademicYear $year, array $data): AcademicYear
    {
        $year->update($data);

        if ($data['is_active'] ?? false) {
            $this->activate($year);
        }

        return $year->fresh();
    }

    public function delete(AcademicYear $year): void
    {
        abort_if($year->classes()->exists(), 422, 'Bu o\'quv yiliga bog\'langan sinflar mavjud.');

        $year->delete();
    }

    /** Joriy o'quv yili — bir vaqtda faqat bittasi faol bo'ladi. */
    public function activate(AcademicYear $year): AcademicYear
    {
        AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
        $year->update(['is_active' => true]);

        return $year->fresh();
    }

    public function currentId(): ?int
    {
        return AcademicYear::active()->value('id');
    }
}
