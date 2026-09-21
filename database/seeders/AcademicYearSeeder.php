<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            ['name' => '2025-2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-05-25', 'is_active' => false],
            ['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-05-25', 'is_active' => true],
        ];

        foreach ($years as $year) {
            AcademicYear::updateOrCreate(['name' => $year['name']], $year);
        }

        $this->command?->info('✅ 2 ta o\'quv yili yaratildi (joriy: 2026-2027).');
    }
}
