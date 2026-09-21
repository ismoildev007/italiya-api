<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * O'qituvchilar + ularning tizim akkauntlari.
 * Ish haqi rejimi aralash: bir qismi fiksed oylik, bir qismi soatbay (dars soniga qarab).
 */
class TeacherSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:bool,4:string}> */
    private const TEACHERS = [
        // [ism, familiya o'zagi, mutaxassislik (fan nomi), ayolmi, ish haqi rejimi]
        ['Umida', 'Shodi', 'Italyan tili', true, 'fixed'],
        ['Alisher', 'Usmon', 'Italyan tili', false, 'fixed'],
        ['Dilnoza', 'Rahim', 'Italyan tili', true, 'per_lesson'],
        ['Sardor', 'Xolmat', 'Italyan tili', false, 'per_lesson'],
        ['Nodira', 'Olim', 'Italyan tili — tibbiyot', true, 'fixed'],
        ['Bekzod', 'Toshmat', 'Italyan tili — agrar', false, 'per_lesson'],
        ['Feruza', 'Yusup', 'Italyan tili — biznes va viza', true, 'fixed'],
        ['Sevara', 'Ismoil', 'Ingliz tili', true, 'per_lesson'],
    ];

    public function run(): void
    {
        mt_srand(2026);

        foreach (self::TEACHERS as $index => [$first, $stem, $specialization, $female, $salaryType]) {
            $lastname = UzbekNames::surname($stem, $female);
            $fatherName = UzbekNames::MALE_FIRST[($index * 3) % count(UzbekNames::MALE_FIRST)];
            $middlename = UzbekNames::patronymic($fatherName, $female);

            $username = Str::lower(Str::ascii($first).'.'.Str::ascii(str_replace('\'', '', $stem)));

            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'school_id' => config('school.id'),
                    'firstname' => $first,
                    'lastname' => $lastname,
                    'middlename' => $middlename,
                    'phone' => UzbekNames::phone(100 + $index),
                    'email' => $username.'@italiya.uz',
                    'password' => Hash::make(UserSeeder::DEFAULT_PASSWORD),
                    'status' => 'active',
                    'locale' => 'uz',
                ]
            );

            $user->roles()->syncWithoutDetaching(['teacher']);

            Teacher::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'school_id' => config('school.id'),
                    'employee_no' => sprintf('OQ-%03d', $index + 1),
                    'specialization' => $specialization,
                    'birth_date' => now()->subYears(28 + ($index % 22))->subDays($index * 11)->toDateString(),
                    'gender' => $female ? 'female' : 'male',
                    'address' => UzbekNames::address(100 + $index),
                    'passport_number' => sprintf('AA%07d', 1234500 + $index),
                    'hired_at' => now()->subYears(1 + ($index % 12))->startOfYear()->addDays(240)->toDateString(),
                    'salary_type' => $salaryType,
                    // Fiksed rejimda oylik maosh, soatbay rejimda bir dars narxi
                    'fixed_salary' => $salaryType === Teacher::SALARY_FIXED ? 4_500_000 + ($index % 6) * 500_000 : 0,
                    'per_lesson_rate' => $salaryType === Teacher::SALARY_PER_LESSON ? 65_000 + ($index % 5) * 10_000 : 0,
                    'is_active' => true,
                ]
            );
        }

        $fixed = Teacher::where('salary_type', 'fixed')->count();
        $hourly = Teacher::where('salary_type', 'per_lesson')->count();

        $this->command?->info('✅ '.count(self::TEACHERS)." ta o'qituvchi yaratildi ({$fixed} fiksed, {$hourly} soatbay).");
    }
}
