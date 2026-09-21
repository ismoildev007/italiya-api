<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tizim akkauntlari: superadmin, direktor (admin) va buxgalter.
 * O'qituvchi/o'quvchi/ota-ona akkauntlari o'z seeder'larida yaratiladi.
 */
class UserSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'parol123';

    public function run(): void
    {
        $accounts = [
            [
                'username' => 'superadmin',
                'firstname' => 'Ismoil',
                'lastname' => 'Usmonov',
                'middlename' => 'Dilshod o`g`li',
                'phone' => '+998880072117',
                'email' => 'superadmin@maktab.uz',
                'password' => 'ismoil_007u',
                'roles' => ['superadmin'],
            ],
            [
                'username' => 'direktor',
                'firstname' => 'Anvar',
                'lastname' => 'Toshmatov',
                'middlename' => 'Solijonovich',
                'phone' => '+998901234567',
                'email' => 'direktor@maktab.uz',
                'password' => self::DEFAULT_PASSWORD,
                'roles' => ['admin'],
            ],
            [
                'username' => 'buxgalter',
                'firstname' => 'Nilufar',
                'lastname' => 'Qodirova',
                'middlename' => 'Baxtiyorovna',
                'phone' => '+998935556677',
                'email' => 'buxgalteriya@maktab.uz',
                'password' => self::DEFAULT_PASSWORD,
                'roles' => ['accountant'],
            ],
        ];

        foreach ($accounts as $account) {
            $roles = $account['roles'];
            unset($account['roles']);

            $user = User::updateOrCreate(
                ['username' => $account['username']],
                array_merge($account, [
                    'password' => Hash::make($account['password']),
                    'school_id' => config('school.id'),
                    'status' => 'active',
                    'locale' => 'uz',
                ])
            );

            $user->roles()->sync($roles);
        }

        $this->command?->info('✅ 3 ta tizim akkaunti yaratildi (superadmin / direktor / buxgalter).');
    }
}
