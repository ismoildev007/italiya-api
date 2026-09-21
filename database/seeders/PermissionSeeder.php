<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Database\Seeder;

/**
 * Permission'lar route'lardan avtomatik generatsiya qilinadi,
 * so'ngra standart rollarga mantiqiy to'plamlar biriktiriladi.
 *
 * Superadmin'ga permission biriktirilmaydi — u barcha tekshiruvlardan
 * tashqarida (Gate::before + PermissionMiddleware).
 */
class PermissionSeeder extends Seeder
{
    /**
     * Rol → ruxsat berilgan permission guruhlari (prefikslar).
     * `*` — barcha permission'lar.
     *
     * @var array<string, array<int, string>>
     */
    private array $matrix = [
        'admin' => ['*'],

        'class_teacher' => [
            'student-', 'school_class-index', 'school_class-show', 'school_class-students',
            'attendance-', 'grade-', 'subject-index',
            'guardian-index', 'guardian-show',
            'test-', 'report-',
            'course_unit-', 'course_material-', 'vocab_deck-', 'game-', 'homework-', 'meeting-', 'certificate-', 'ai-status',
        ],

        'teacher' => [
            'student-index', 'student-show', 'student-change_level', 'student-level_history',
            'school_class-index', 'school_class-show', 'school_class-students',
            'subject-index', 'subject-show',
            'attendance-index', 'attendance-show', 'attendance-store', 'attendance-bulk_store', 'attendance-journal',
            'grade-', 'lesson-', 'test-', 'report-attendance', 'report-grades', 'report-skills', 'report-monthly_progress',
            // Til o'quv markazi modullari
            'course_unit-', 'course_material-', 'vocab_deck-', 'game-', 'homework-', 'meeting-',
            'certificate-index', 'certificate-show', 'certificate-issue', 'ai-status',
        ],

        'accountant' => [
            'student-index', 'student-show',
            'school_class-index', 'school_class-show',
            'tariff-', 'invoice-', 'payment-', 'discount-', 'finance-',
            'report-payments', 'report-debtors', 'teacher_salary-',
        ],

        'parent' => [
            'portal-',
        ],

        'student' => [
            'portal-',
        ],
    ];

    public function run(PermissionService $permissions): void
    {
        $result = $permissions->syncFromRoutes(prune: true);

        $all = Permission::pluck('name')->all();

        foreach ($this->matrix as $roleName => $prefixes) {
            $role = Role::find($roleName);

            if (! $role) {
                continue;
            }

            $granted = in_array('*', $prefixes, true)
                ? $all
                : array_values(array_filter($all, function (string $permission) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        if ($permission === $prefix || str_starts_with($permission, $prefix)) {
                            return true;
                        }
                    }

                    return false;
                }));

            $role->permissions()->sync($granted);

            $this->command?->line("   • {$roleName}: ".count($granted).' ta ruxsat');
        }

        $this->command?->info("✅ {$result['total']} ta permission route'lardan generatsiya qilindi.");
    }
}
