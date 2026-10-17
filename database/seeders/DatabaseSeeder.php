<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * To'liq demo ma'lumotlar to'plami: `php artisan migrate:fresh --seed`
 *
 * Tartib muhim — har bir seeder o'zidan oldingilariga tayanadi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. RBAC — rollar va route'lardan generatsiya qilingan ruxsatlar
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,

            // 2. Akademiya strukturasi (guruhlar = daraja × yo'nalish)
            AcademicYearSeeder::class,
            SubjectSeeder::class,
            TeacherSeeder::class,
            SchoolClassSeeder::class,
            ClassSubjectTeacherSeeder::class,
            StudentSeeder::class,
            GuardianSeeder::class,

            // 3. Moliya
            TariffSeeder::class,
            StudentTariffSeeder::class,
            FinanceSeeder::class,

            // 4. O'quv jarayoni
            LessonSeeder::class,
            AttendanceSeeder::class,
            GradeSeeder::class,
            TestSeeder::class,
            CourseUnitSeeder::class,
            VocabSeeder::class,
            HomeworkSeeder::class,
            CertificateSeeder::class,

            // 5. Ish haqi
            TeacherSalarySeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('🇮🇹 Italiya Akademiyasi LMS demo bazasi tayyor.');
        $this->command?->table(
            ['Login', 'Parol', 'Rol'],
            [
                ['superadmin', 'ismoil_007u', 'Superadmin'],
                ['direktor', UserSeeder::DEFAULT_PASSWORD, 'Administrator (direktor)'],
                ['buxgalter', UserSeeder::DEFAULT_PASSWORD, 'Buxgalter / kassir'],
                ['umida.shodi', UserSeeder::DEFAULT_PASSWORD, 'O\'qituvchi (Italyan tili)'],
                ['o2026-0001 yoki +998994602923', UserSeeder::DEFAULT_PASSWORD, 'O\'quvchi (A1-Ertalab)'],
            ]
        );
    }
}
