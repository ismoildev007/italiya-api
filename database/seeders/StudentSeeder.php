<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentLevelHistory;
use App\Models\User;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * O'quvchilar + ularning shaxsiy akkauntlari (`student` roli — faqat o'qish huquqi).
 * Sinf tarixi ham yoziladi (arxiv yo'qotilmaydi).
 */
class StudentSeeder extends Seeder
{
    private const PER_CLASS = 12;

    public function run(): void
    {
        mt_srand(20260908);

        $year = AcademicYear::active()->firstOrFail();
        $classes = SchoolClass::orderBy('id')->get();
        $languages = array_keys(config('academy.translation_languages'));

        $counter = 0;
        $password = Hash::make(UserSeeder::DEFAULT_PASSWORD);

        foreach ($classes as $class) {
            for ($i = 0; $i < self::PER_CLASS; $i++) {
                $counter++;

                $female = ($counter % 2) === 0;
                $first = $female
                    ? UzbekNames::FEMALE_FIRST[($counter * 7) % count(UzbekNames::FEMALE_FIRST)]
                    : UzbekNames::MALE_FIRST[($counter * 5) % count(UzbekNames::MALE_FIRST)];

                $stem = UzbekNames::SURNAME_STEMS[($counter * 3) % count(UzbekNames::SURNAME_STEMS)];
                $lastname = UzbekNames::surname($stem, $female);
                $father = UzbekNames::MALE_FIRST[($counter * 11) % count(UzbekNames::MALE_FIRST)];
                $middlename = UzbekNames::patronymic($father, $female);

                $studentNo = sprintf('%d-%04d', $year->starts_on->year, $counter);
                $username = 'o'.$studentNo;

                // O'quvchi tizimga o'z akkaunti bilan kiradi (read-only)
                $user = User::updateOrCreate(
                    ['username' => $username],
                    [
                        'school_id' => config('school.id'),
                        'firstname' => $first,
                        'lastname' => $lastname,
                        'middlename' => $middlename,
                        'phone' => UzbekNames::phone(5000 + $counter),
                        'password' => $password,
                        'status' => 'active',
                        'locale' => 'uz',
                    ]
                );

                $user->roles()->syncWithoutDetaching(['student']);

                $student = Student::updateOrCreate(
                    ['student_no' => $studentNo],
                    [
                        'school_id' => config('school.id'),
                        'user_id' => $user->id,
                        'class_id' => $class->id,
                        'level' => $class->level,
                        'track' => $class->track,
                        'monthly_hours' => 16 + ($counter * 7) % 20,
                        'preferred_language' => $languages[$counter % 5 === 0 ? 1 : ($counter % 7 === 0 ? 2 : 0)],
                        // Har uchinchi o'quvchi Telegram botga bog'langan
                        'telegram_chat_id' => $counter % 3 === 0 ? (string) (700000000 + $counter) : null,
                        'firstname' => $first,
                        'lastname' => $lastname,
                        'middlename' => $middlename,
                        // Til kurslari: 17–38 yoshli o'quvchilar
                        'birth_date' => now()->subYears(17 + ($counter * 7) % 22)->subDays(($counter * 13) % 360)->toDateString(),
                        'gender' => $female ? 'female' : 'male',
                        'phone' => UzbekNames::phone(5000 + $counter),
                        'address' => UzbekNames::address($counter),
                        // face_id / card_id hozircha bo'sh — kelajakdagi Face ID/turniket uchun tayyor
                        'face_id' => null,
                        'card_id' => null,
                        'enrolled_at' => $year->starts_on->toDateString(),
                        'status' => 'active',
                    ]
                );

                StudentLevelHistory::firstOrCreate(
                    ['student_id' => $student->id, 'to_level' => $class->level],
                    ['school_id' => config('school.id'), 'from_level' => null, 'reason' => 'placement', 'changed_at' => $year->starts_on]
                );

                StudentClassHistory::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'academic_year_id' => $year->id,
                    ],
                    [
                        'school_id' => config('school.id'),
                        'from_date' => $year->starts_on->toDateString(),
                        'to_date' => null,
                        'reason' => 'enrolled',
                    ]
                );
            }
        }

        $this->command?->info("✅ {$counter} ta o'quvchi yaratildi (har biriga akkaunt: telefon + parol, daraja va guruh tarixi).");
    }
}
