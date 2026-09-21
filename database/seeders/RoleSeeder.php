<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Standart (tizim) rollari — inglizcha texnik nomlar bilan.
 *
 * Bu rollar `is_system = true` bo'ladi va HECH QACHON o'chirilmaydi:
 *  - Role modelida `deleting` hodisasi bloklaydi
 *  - RoleService::delete() 403 qaytaradi
 *  - config('authorization.system_roles') ro'yxatida qayd etilgan
 *
 * Rolning ko'rinadigan nomini (`title`) admin panelda o'zgartirish mumkin,
 * lekin texnik `name` o'zgarmaydi — kod shu nomga tayanadi.
 */
class RoleSeeder extends Seeder
{
    /**
     * @var array<int, array{name:string, title:string, description:string, level:int}>
     */
    public const ROLES = [
        [
            'name' => 'superadmin',
            'title' => 'Superadmin',
            'description' => 'Tizim egasi. Barcha cheklovlardan tashqarida: istalgan bo\'limga kiradi, rol yaratadi va ruxsat biriktiradi.',
            'level' => 1,
        ],
        [
            'name' => 'admin',
            'title' => 'Administrator (direktor)',
            'description' => 'O\'quv markazi direktori. Barcha modullarni boshqaradi.',
            'level' => 10,
        ],
        [
            'name' => 'class_teacher',
            'title' => 'Guruh rahbari',
            'description' => 'O\'z guruhi o\'quvchilarini, davomatini, testlari va uy ishlarini boshqaradi.',
            'level' => 20,
        ],
        [
            'name' => 'teacher',
            'title' => 'O\'qituvchi',
            'description' => 'Italyan tili o\'qituvchisi. Darslik va testlarni AI bilan yaratadi/tahrirlaydi, uy ishi beradi, o\'yin tuzadi, baholaydi.',
            'level' => 30,
        ],
        [
            'name' => 'accountant',
            'title' => 'Buxgalter / kassir',
            'description' => 'To\'lovlar, hisob-fakturalar, chegirmalar va qarzdorlik bilan ishlaydi.',
            'level' => 40,
        ],
        [
            'name' => 'parent',
            'title' => 'Ota-ona',
            'description' => 'Faqat o\'z farzandiga tegishli ma\'lumotlarni ko\'radi (mobil ilova).',
            'level' => 50,
        ],
        [
            'name' => 'student',
            'title' => 'O\'quvchi',
            'description' => 'Telefon + parol bilan kiradi. O\'z darajasi va guruhiga mos darsliklar, testlar, o\'yinlar, uy ishlarini ko\'radi va yechadi.',
            'level' => 60,
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::withoutEvents(function () use ($role) {
                Role::updateOrCreate(
                    ['name' => $role['name']],
                    [
                        'title' => $role['title'],
                        'description' => $role['description'],
                        'level' => $role['level'],
                        'is_system' => true,   // o'chirib bo'lmaydi
                        'school_id' => config('school.id'),
                    ]
                );
            });
        }

        $this->command?->info('✅ '.count(self::ROLES).' ta tizim roli yaratildi (o\'chirib bo\'lmaydi).');
    }
}
