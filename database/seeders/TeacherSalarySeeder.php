<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Services\SalaryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Oxirgi 3 oy uchun ish haqi hisob-kitobi.
 * Fiksed rejimdagilar oylik maosh, soatbaylar o'tkazilgan darslar soniga qarab.
 */
class TeacherSalarySeeder extends Seeder
{
    public function run(SalaryService $salaries): void
    {
        $teachers = Teacher::where('is_active', true)->get();
        $created = 0;

        DB::transaction(function () use ($teachers, $salaries, &$created) {
            foreach ([2, 1, 0] as $offset) {
                $date = now()->subMonths($offset);

                foreach ($teachers as $index => $teacher) {
                    // Ba'zilarga mukofot, ba'zilarga ushlab qolish
                    $bonus = $index % 5 === 0 ? 500_000 : 0;
                    $deduction = $index % 7 === 0 ? 150_000 : 0;

                    $salary = $salaries->generate($teacher, $date->year, $date->month, $bonus, $deduction);

                    // O'tgan oylar to'langan, joriy oy kutilmoqda
                    if ($offset > 0) {
                        $salary->update([
                            'status' => TeacherSalary::STATUS_PAID,
                            'paid_at' => $date->copy()->endOfMonth()->setTime(16, 0),
                            'note' => $bonus > 0 ? 'Mukofot bilan birga to\'landi' : null,
                        ]);
                    }

                    $created++;
                }
            }
        });

        $paid = number_format((float) TeacherSalary::where('status', 'paid')->sum('total_amount'), 0, '.', ' ');
        $pending = TeacherSalary::where('status', 'pending')->count();

        $this->command?->info("✅ {$created} ta ish haqi yozuvi ({$paid} so'm to'langan, {$pending} ta kutilmoqda).");
    }
}
