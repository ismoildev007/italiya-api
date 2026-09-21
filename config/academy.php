<?php

/*
|--------------------------------------------------------------------------
| Italiya Akademiyasi — til o'quv markazi konfiguratsiyasi
|--------------------------------------------------------------------------
| CEFR darajalari, ko'nikmalar (IELTS uslubida), yo'nalishlar, tarjima
| tillari, urinish/eshitish limitlari va sertifikat qoidalari.
| Barcha qiymatlar .env orqali o'zgartirilishi mumkin.
*/

return [
    /* Asosiy o'rganilayotgan til */
    'target_language' => env('ACADEMY_TARGET_LANGUAGE', 'it'),

    /* Kartochkaning orqa tomoni / tarjima tillari (ko'p tillilik) */
    'translation_languages' => [
        'uz' => 'O\'zbekcha',
        'en' => 'English',
        'ru' => 'Русский',
    ],
    'default_translation_language' => env('ACADEMY_DEFAULT_TRANSLATION', 'uz'),

    /* CEFR darajalari — tartib muhim (ordinal shu tartibdan olinadi) */
    'levels' => [
        'A1' => ['ordinal' => 1, 'title' => 'Boshlang\'ich (A1)', 'description' => 'Principiante — oddiy iboralar va kundalik so\'zlar.'],
        'A2' => ['ordinal' => 2, 'title' => 'Elementar (A2)', 'description' => 'Elementare — kundalik vaziyatlarda muloqot.'],
        'B1' => ['ordinal' => 3, 'title' => 'O\'rta (B1)', 'description' => 'Intermedio — mustaqil foydalanuvchi.'],
        'B2' => ['ordinal' => 4, 'title' => 'Yuqori o\'rta (B2)', 'description' => 'Intermedio superiore — erkin muloqot.'],
        'C1' => ['ordinal' => 5, 'title' => 'Ilg\'or (C1)', 'description' => 'Avanzato — murakkab matnlar va professional muloqot.'],
        'C2' => ['ordinal' => 6, 'title' => 'Mukammal (C2)', 'description' => 'Padronanza — ona tili darajasi.'],
    ],

    /* IELTS uslubidagi ko'nikmalar + grammatika va lug'at */
    'skills' => [
        'listening' => 'Tinglash (Ascolto)',
        'reading' => 'O\'qish (Lettura)',
        'writing' => 'Yozish (Scrittura)',
        'speaking' => 'Gapirish (Parlato)',
        'grammar' => 'Grammatika (Grammatica)',
        'vocabulary' => 'Lug\'at (Lessico)',
    ],

    /* Baholanadigan asosiy 4 ko'nikma (sertifikat va progress uchun) */
    'core_skills' => ['listening', 'reading', 'writing', 'speaking'],

    /* Yo'nalishlar (mockupdagi Tibbiyot / Fermer / Maxsus guruhlar) */
    'tracks' => [
        'general' => 'Umumiy italyan tili',
        'medical' => 'Tibbiyot xodimlari uchun',
        'farming' => 'Fermer / agrar yo\'nalish',
        'special' => 'Maxsus (biznes, universitet, viza)',
    ],

    /* Materiallar turi bo'yicha standart limitlar (o'qituvchi har birida o'zgartira oladi) */
    'defaults' => [
        // Darslik (course unit): audio/video necha marta tinglanadi (null = cheksiz)
        'unit_max_plays' => (int) env('ACADEMY_UNIT_MAX_PLAYS', 3),
        // Test turlari bo'yicha: urinishlar soni va eshitish soni
        'kinds' => [
            'practice' => ['max_attempts' => null, 'max_plays' => 2, 'auto_grade_ai' => true],
            'homework' => ['max_attempts' => 2, 'max_plays' => 2, 'auto_grade_ai' => true],
            'test' => ['max_attempts' => 1, 'max_plays' => 1, 'auto_grade_ai' => true],
            'mock' => ['max_attempts' => 1, 'max_plays' => 1, 'auto_grade_ai' => true],
            'placement' => ['max_attempts' => 1, 'max_plays' => 1, 'auto_grade_ai' => true],
        ],
        // Bo'limlar bo'yicha standart vaqt (daqiqa) — IELTS uslubida
        'section_minutes' => [
            'listening' => 20,
            'reading' => 30,
            'writing' => 30,
            'speaking' => 10,
            'grammar' => 15,
            'vocabulary' => 10,
        ],
    ],

    /* O'quvchi darajasini "o'tgan" deb hisoblash uchun minimal foiz */
    'pass_percent' => (int) env('ACADEMY_PASS_PERCENT', 60),

    /* Sertifikat generatori: oyiga 1 marta, oyning 25-sanasidan keyin */
    'certificate' => [
        'available_from_day' => (int) env('CERTIFICATE_AVAILABLE_FROM_DAY', 25),
        'per_month' => 1,
        'min_attempts' => (int) env('CERTIFICATE_MIN_ATTEMPTS', 1),
        'signer_name' => env('CERTIFICATE_SIGNER', 'Direktor'),
    ],

    /* Oylik hisobot ota-onaga yuboriladigan kun */
    'monthly_report_day' => (int) env('ACADEMY_MONTHLY_REPORT_DAY', 28),

    /* Qarzdorlik eslatmasi kunlari (har oy) */
    'debt_reminder_days' => array_map('intval', explode(',', env('ACADEMY_DEBT_REMINDER_DAYS', '5,15,25'))),

    /* O'yinlar */
    'games' => [
        'types' => [
            'flashcard' => 'Kartochka (so\'z ↔ tarjima)',
            'match' => 'Juftlash (so\'zni tarjimasi bilan)',
            'choice' => 'Variantli (4 tadan birini tanlash)',
            'speech' => 'Talaffuz (mikrofon orqali)',
            'listening' => 'Eshitib yozish (diktant)',
            'typing' => 'Yozib javob berish',
        ],
        'default_card_count' => 10,
        'default_seconds_per_card' => 15,
    ],
];
