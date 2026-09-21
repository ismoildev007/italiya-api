<?php

namespace Database\Seeders;

use App\Models\Subject;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UzbekNames::SUBJECTS as [$name, $code]) {
            Subject::updateOrCreate(
                ['name' => $name],
                ['code' => $code, 'is_active' => true]
            );
        }

        $this->command?->info('✅ '.count(UzbekNames::SUBJECTS).' ta fan yaratildi.');
    }
}
