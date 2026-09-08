<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    // Database\Seeders\UserSeeder.php ichida
    public function run(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin'], [
            'type' => 0,
            'description' => 'Tizim super administratori',
        ]);

        $guardRole = Role::firstOrCreate(['name' => 'guard'], [
            'type' => 1,
            'description' => 'Oddiy foydalanuvchi',
        ]);

        $superadmin = User::firstOrCreate(['username' => 'superadmin'], [
            'firstname' => 'Superadmin',
            'lastname'  => 'Adminov',
            'password'  => bcrypt('superadmin007'),
        ]);

        $guard = User::firstOrCreate(['username' => 'guard'], [
            'firstname' => 'Qorovul',
            'lastname'  => "Qo'riqchiboyev",
            'password'  => bcrypt('guard007'),
        ]);

        $superadmin->roles()->sync(['superadmin']);
        $guard->roles()->sync(['guard']);

        $this->command->info('✅ Rollar foydalanuvchilarga muvaffaqiyatli biriktirildi.');
    }
}
