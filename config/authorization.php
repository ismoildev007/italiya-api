<?php

use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Avtorizatsiya (RBAC) konfiguratsiyasi
|--------------------------------------------------------------------------
| Permission'lar route'lardan avtomatik generatsiya qilinadi
| (`php artisan permissions:sync`). Quyidagi ro'yxatlar generatsiyadan
| va tekshiruvdan chetlashtiriladigan joylarni belgilaydi.
*/

return [
    // Bu kontrollerlarning barcha action'lari permission talab qilmaydi
    'free_controllers' => [
        AuthController::class,
    ],

    // Bu permission nomlari hech qachon yaratilmaydi/tekshirilmaydi
    'free_routes' => [
        'auth-login',
        'auth-logout',
        'auth-me',
    ],

    // Hech qachon o'chirib bo'lmaydigan tizim rollari (seeder yaratadi).
    // `is_system = true` bo'lgan rollar UI orqali ham o'chirilmaydi/nomi o'zgarmaydi.
    'system_roles' => [
        'superadmin',
        'admin',
        'class_teacher',
        'teacher',
        'accountant',
        'parent',
        'student',
    ],

    // Barcha cheklovlardan tashqarida bo'lgan rol
    'super_role' => 'superadmin',
];
