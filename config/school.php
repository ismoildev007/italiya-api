<?php

/*
|--------------------------------------------------------------------------
| Maktab (white-label) konfiguratsiyasi
|--------------------------------------------------------------------------
| Har bir maktab alohida serverga, alohida bazaga deploy qilinadi.
| Shu faylning qiymatlari .env orqali sozlanadi — kodga tegmasdan
| yangi maktabga deploy qilish uchun yetarli.
|
| school_id — kelajakdagi haqiqiy multi-tenant uchun tayyorlangan.
| Hozircha har bir instansiyada doim bitta qiymat (default 1) ishlatiladi,
| lekin barcha jadvallarda `school_id` ustuni mavjud.
*/

return [
    'id' => (int) env('SCHOOL_ID', 1),

    'name' => env('SCHOOL_NAME', 'Maktab'),
    'short_name' => env('SCHOOL_SHORT_NAME', 'Maktab'),
    'logo' => env('SCHOOL_LOGO', '/images/logo.png'),
    'phone' => env('SCHOOL_PHONE', ''),
    'email' => env('SCHOOL_EMAIL', ''),
    'address' => env('SCHOOL_ADDRESS', ''),
    'website' => env('SCHOOL_WEBSITE', ''),
    // Rahbar (sidebar / yordam kartasi) va Telegram qo'llab-quvvatlash havolasi
    'director' => env('SCHOOL_DIRECTOR', ''),
    'telegram' => env('SCHOOL_TELEGRAM', ''),

    'currency' => env('SCHOOL_CURRENCY', 'UZS'),
    'timezone' => env('SCHOOL_TIMEZONE', 'Asia/Tashkent'),
    'locale' => env('APP_LOCALE', 'uz'),

    // Hisob-fakturalar har oyning shu kunida generatsiya qilinadi
    'invoice_generation_day' => (int) env('SCHOOL_INVOICE_DAY', 1),

    // Darslar boshlanishi (smenalar)
    'shifts' => [
        1 => ['label' => '1-smena', 'starts_at' => '08:00'],
        2 => ['label' => '2-smena', 'starts_at' => '13:30'],
    ],
];
