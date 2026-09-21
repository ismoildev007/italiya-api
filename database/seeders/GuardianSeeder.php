<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ota-onalar + ularning akkauntlari (mobil ilova uchun).
 * Bir qismi Telegram botga "bog'langan" (telegram_chat_id mavjud),
 * qolganlarida bog'lash kodi (`link_code`) kutilmoqda.
 */
class GuardianSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(777);

        $students = Student::orderBy('id')->get();
        $password = Hash::make(UserSeeder::DEFAULT_PASSWORD);
        $created = 0;
        $linked = 0;

        foreach ($students as $index => $student) {
            // Familiya farzandnikiga mos (otasi)
            $lastStem = Str::of($student->lastname)->replaceMatches('/(ev|ov)a?$/u', '')->toString();
            $fatherFirst = Str::of($student->middlename)->replaceMatches('/(ovich|ovna)$/u', '')->toString()
                ?: UzbekNames::MALE_FIRST[$index % count(UzbekNames::MALE_FIRST)];

            $guardians = [[
                'firstname' => $fatherFirst,
                'lastname' => UzbekNames::surname($lastStem, false),
                'relation' => 'father',
                'is_primary' => true,
            ]];

            // Har uchinchi o'quvchida onasi ham ro'yxatdan o'tgan
            if ($index % 3 === 0) {
                $guardians[] = [
                    'firstname' => UzbekNames::FEMALE_FIRST[($index * 5) % count(UzbekNames::FEMALE_FIRST)],
                    'lastname' => UzbekNames::surname($lastStem, true),
                    'relation' => 'mother',
                    'is_primary' => false,
                ];
            }

            foreach ($guardians as $position => $data) {
                $seed = 20000 + ($index * 2) + $position;
                $phone = UzbekNames::phone($seed);
                $isLinked = ($seed % 5) !== 0; // ~80% botga bog'langan

                $username = 'ota'.$seed;

                $user = User::updateOrCreate(
                    ['username' => $username],
                    [
                        'school_id' => config('school.id'),
                        'firstname' => $data['firstname'],
                        'lastname' => $data['lastname'],
                        'phone' => $phone,
                        'password' => $password,
                        'status' => 'active',
                        'locale' => 'uz',
                    ]
                );

                $user->roles()->syncWithoutDetaching(['parent']);

                $guardian = Guardian::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'school_id' => config('school.id'),
                        'firstname' => $data['firstname'],
                        'lastname' => $data['lastname'],
                        'middlename' => UzbekNames::patronymic(
                            UzbekNames::MALE_FIRST[($seed * 3) % count(UzbekNames::MALE_FIRST)],
                            $data['relation'] === 'mother'
                        ),
                        'phone' => $phone,
                        'workplace' => $data['relation'] === 'father' ? 'Xususiy tadbirkor' : 'Uy bekasi',
                        // Telegram bot integratsiyasi (stub): bog'langanlarda chat_id, qolganlarida kod
                        'telegram_chat_id' => $isLinked ? (string) (600000000 + $seed) : null,
                        'link_code' => $isLinked ? null : Str::upper(Str::random(8)),
                        'linked_at' => $isLinked ? now()->subDays($seed % 200) : null,
                    ]
                );

                $guardian->students()->syncWithoutDetaching([
                    $student->id => [
                        'school_id' => config('school.id'),
                        'relation' => $data['relation'],
                        'is_primary' => $data['is_primary'],
                    ],
                ]);

                $created++;
                $isLinked && $linked++;
            }
        }

        $this->command?->info("✅ {$created} ta ota-ona yaratildi ({$linked} tasi Telegram botga bog'langan).");
    }
}
