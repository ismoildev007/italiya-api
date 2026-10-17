<?php

/*
|--------------------------------------------------------------------------
| Tizim qo'llanmasi — har bir bo'lim nima uchun va qanday boshqariladi
|--------------------------------------------------------------------------
| `GET /guide` foydalanuvchining rol/ruxsatlariga qarab faqat unga ochiq
| bo'limlarni qaytaradi. `route` — frontend sahifasi, `permission` — kerakli
| ruxsat(lar)dan kamida bittasi, `role` — rolga bog'liq bo'lim (student).
*/

return [
    'groups' => [
        ['key' => 'start', 'title' => 'Boshlash', 'icon' => 'pi pi-compass'],
        ['key' => 'study', 'title' => 'O\'quv jarayoni', 'icon' => 'pi pi-book'],
        ['key' => 'content', 'title' => 'Kontent (darslik, test, lug\'at, o\'yin)', 'icon' => 'pi pi-pencil'],
        ['key' => 'people', 'title' => 'Odamlar', 'icon' => 'pi pi-users'],
        ['key' => 'finance', 'title' => 'Moliya', 'icon' => 'pi pi-wallet'],
        ['key' => 'system', 'title' => 'Tizim va xavfsizlik', 'icon' => 'pi pi-shield'],
        ['key' => 'student', 'title' => 'O\'quvchi kabineti', 'icon' => 'pi pi-graduation-cap'],
    ],

    'sections' => [
        [
            'key' => 'overview', 'group' => 'start', 'title' => 'Tizim qanday ishlaydi', 'icon' => 'pi pi-info-circle', 'route' => '/',
            'summary' => 'Italiya Akademiyasi LMS: o\'quvchi telefon + parol bilan kiradi, o\'z darajasi (A1–C2) va guruhiga mos darslarni, testlarni, lug\'atni va o\'yinlarni ko\'radi. Admin va o\'qituvchi shu panel orqali hamma narsani boshqaradi.',
            'steps' => [
                'Avval guruh yarating (daraja + yo\'nalish), keyin o\'quvchi qo\'shing — u avtomatik guruh darajasini oladi.',
                'Kontent bo\'limida darslik, test, lug\'at va o\'yin tuzing — ular o\'quvchiga darajasi bo\'yicha ko\'rinadi.',
                'Natijalar, davomat va uy ishi sertifikat baliga (davomat 40% + uy ishi 30% + test 30%) ta\'sir qiladi.',
                'Ota-onaga Telegram orqali qarzdorlik, baho va oylik hisobot boradi (bot sozlangan bo\'lsa).',
            ],
            'tips' => ['Chap menyuda faqat sizning rolingizga ruxsat etilgan bo\'limlar ko\'rinadi.', 'Har bir sahifaning yuqorisida qidiruv va filtrlar bor.'],
        ],
        [
            'key' => 'groups', 'group' => 'study', 'title' => 'Guruhlar', 'icon' => 'pi pi-th-large', 'route' => '/classes', 'permission' => 'school_class-index',
            'summary' => 'Guruh = daraja (A1–C2) × yo\'nalish (umumiy, tibbiyot, fermer, maxsus) + dars jadvali, xona, Zoom havola va guruh rahbari.',
            'steps' => ['«Qo\'shish» → nom (masalan A1-Ertalab), daraja, yo\'nalish, kunlar va vaqt, o\'qituvchi.', 'Guruh sahifasida o\'quvchilar ro\'yxati, davomat jurnali va materiallar bor.', 'Guruh darajasini o\'zgartirsangiz, yangi o\'quvchilar shu darajani oladi; eskilarini «O\'quvchilar» da alohida o\'zgartiring.'],
            'tips' => ['Zoom havolani guruhga yozib qo\'ysangiz, o\'quvchi kabinetida darhol ko\'rinadi.'],
        ],
        [
            'key' => 'students', 'group' => 'study', 'title' => 'O\'quvchilar bazasi', 'icon' => 'pi pi-users', 'route' => '/students', 'permission' => 'student-index',
            'summary' => 'O\'quvchi profili: daraja, guruh, telefon (login), ota-ona raqami, oylik soat, qurilma bog\'lanishi, XP va natijalar.',
            'steps' => ['«Qo\'shish» → ism, telefon, guruh, ota-ona telefoni; «akkaunt yaratish» belgilansa parol beriladi.', 'O\'quvchi telefon raqami + parol bilan kiradi va birinchi qurilmaga bog\'lanadi; telefon almashsa — «Qurilmani tozalash».', 'Daraja o\'zgartirish: profil → «Daraja» (A1→A2…) — tarix saqlanadi va xabar yuboriladi.', 'Profilda ko\'nikma foizlari, testlar, uy ishlari, to\'lovlar va sertifikatlar ko\'rinadi.'],
            'tips' => ['Qarzdor o\'quvchilarni «Qarzdorlar» filtri bilan ajrating.', 'Bloklash — «Foydalanuvchilar» bo\'limida holatni o\'zgartirish.'],
        ],
        [
            'key' => 'attendance', 'group' => 'study', 'title' => 'Davomat', 'icon' => 'pi pi-check-square', 'route' => '/attendance', 'permission' => 'attendance-journal',
            'summary' => 'Guruh bo\'yicha kunlik davomat jurnali. Davomat sertifikat balining 40% ini tashkil qiladi va ota-onaga xabar boradi.',
            'steps' => ['Guruh va sanani tanlang → har bir o\'quvchiga keldi / kelmadi / kech qoldi / sababli.', '«Saqlash» — hammasi bir vaqtda yoziladi.'],
        ],
        [
            'key' => 'homeworks', 'group' => 'study', 'title' => 'Uy ishlari', 'icon' => 'pi pi-inbox', 'route' => '/homeworks', 'permission' => 'homework-index',
            'summary' => 'Guruhga yoki bitta o\'quvchiga uy ishi: matn, fayl, test yoki darslik biriktiriladi. O\'quvchi topshiradi, o\'qituvchi 0–100 ball qo\'yadi.',
            'steps' => ['«Qo\'shish» → sarlavha, guruh/o\'quvchi, muddat, kerak bo\'lsa test shabloni.', 'Topshiriqlar ro\'yxatida «Baholash» → ball va izoh; o\'quvchi va ota-onaga xabar ketadi.'],
            'tips' => ['Uy ishi o\'rtachasi sertifikat balining 30% i.'],
        ],
        [
            'key' => 'meetings', 'group' => 'study', 'title' => 'Zoom mitinglar', 'icon' => 'pi pi-video', 'route' => '/meetings', 'permission' => 'meeting-index',
            'summary' => 'Onlayn darslar: havola, vaqt, davomiylik; guruhlar, darajalar yoki yo\'nalishlar bo\'yicha ko\'rsatiladi.',
            'steps' => ['«Qo\'shish» → sarlavha, Zoom havola, sana-vaqt, kimlarga (guruh/daraja/yo\'nalish).', 'O\'quvchi kabinetida «Zoom» bo\'limida kelgusi darslar chiqadi, Telegramga ham xabar boradi.'],
        ],
        [
            'key' => 'units', 'group' => 'content', 'title' => 'Darsliklar', 'icon' => 'pi pi-book', 'route' => '/units', 'permission' => 'course_unit-index',
            'summary' => 'Har daraja × ko\'nikma (o\'qish, yozish, gapirish, grammatika, lug\'at) uchun dars matni (markdown) va unga biriktirilgan mashq testi. Darslik + lug\'at + mashq testidan o\'quvchining «Dars yo\'li» avtomatik tuziladi.',
            'steps' => ['«Qo\'shish» → daraja, ko\'nikma, sarlavha, matn; «Nashr qilish» belgilansa o\'quvchiga ko\'rinadi.', 'Mashq testi biriktirsangiz, dars yo\'lidagi mashqlar shu testdan olinadi.', 'Tartib raqami darslar ketma-ketligini belgilaydi.'],
            'tips' => ['Tinglash (audio) bo\'limi mijoz talabi bilan o\'chirilgan — AUDIO_ENABLED=true bilan yoqiladi.'],
        ],
        [
            'key' => 'tests', 'group' => 'content', 'title' => 'Testlar', 'icon' => 'pi pi-file-edit', 'route' => '/tests', 'permission' => 'test-index',
            'summary' => 'IELTS uslubidagi testlar: bo\'limlar (o\'qish, yozish, gapirish, grammatika, lug\'at), savol turlari (variant, vero/falso, bo\'sh joy, insho, gapirish). Turlar: mashq (cheksiz), uy ishi (2 urinish), nazorat va mock (1 urinish).',
            'steps' => ['«Qo\'shish» → sarlavha, daraja, tur, vaqt → bo\'lim qo\'shing → savollar va to\'g\'ri javoblar.', '«Nashr qilish» → «Biriktirish» (guruh yoki o\'quvchi, muddat) — o\'quvchiga xabar boradi.', 'Natijalar: biriktirma → urinishlar; insho/gapirish javoblarini «Baholash» bilan qo\'lda tekshiring.'],
            'tips' => ['Variantli testlarni o\'quvchi Telegram botda ham yecha oladi.', 'AI generatsiya tugmasi AI_ENABLED=true bo\'lganda chiqadi.'],
        ],
        [
            'key' => 'decks', 'group' => 'content', 'title' => 'Lug\'at (kartochkalar)', 'icon' => 'pi pi-language', 'route' => '/decks', 'permission' => 'vocab_deck-index',
            'summary' => 'To\'plam = daraja + mavzu; kartochka = italyancha so\'z + tarjimalar (uz/en/ru) + misol. Lug\'at «so\'z moslashtirish», duel va dars yo\'li mashqlarining manbai.',
            'steps' => ['«Qo\'shish» → nom, daraja → kartochkalarni qo\'lda kiriting yoki Excel/CSV yuklang (so\'z | tarjima | en | ru | misol).', '«Nashr qilish» — o\'quvchi o\'z darajasi va pastidagi to\'plamlarni ko\'radi.'],
            'tips' => ['Har darajada kamida 8–10 ta kartochka bo\'lsa o\'yinlar to\'liq ishlaydi.'],
        ],
        [
            'key' => 'games', 'group' => 'content', 'title' => 'O\'yinlar va duel', 'icon' => 'pi pi-bolt', 'route' => '/games', 'permission' => 'game-index',
            'summary' => '«So\'z moslashtirish» o\'yini: har daraja uchun to\'plam, savollar soni, vaqt. Duel: o\'quvchi sherigini taklif qiladi yoki bot (oson/o\'rta/kuchli) bilan o\'ynaydi — kim birinchi to\'g\'ri javob bersa, raundni yutadi; g\'olib XP oladi.',
            'steps' => ['«Qo\'shish» → nom, daraja, to\'plam, savollar soni, bir savolga vaqt.', 'Natijalar va liderbord o\'yin sahifasida; duel statistikasi o\'quvchi profilida (g\'alaba/mag\'lubiyat, XP).'],
            'tips' => ['Duel savollari avtomatik: nashr qilingan lug\'at + testlardagi variantli savollar.'],
        ],
        [
            'key' => 'certificates', 'group' => 'content', 'title' => 'Sertifikatlar', 'icon' => 'pi pi-verified', 'route' => '/certificates', 'permission' => 'certificate-index',
            'summary' => 'O\'quvchi oyiga 1 marta, oyning 25-sanasidan keyin o\'zi sertifikat oladi. Ball = davomat 40% + uy ishi 30% + test 30%; ko\'nikmalar kesimi ham chiqadi. QR kod bilan tekshiriladi.',
            'steps' => ['Admin «Berish» tugmasi bilan muddatdan oldin ham bera oladi (oyiga 1 ta qoidasi saqlanadi).', '«HTML» — chop etish / PDF.'],
        ],
        [
            'key' => 'teachers', 'group' => 'people', 'title' => 'O\'qituvchilar', 'icon' => 'pi pi-user-edit', 'route' => '/teachers', 'permission' => 'teacher-index',
            'summary' => 'O\'qituvchi akkaunti (login + parol), mutaxassislik, ish haqi rejimi (oylik / darsbay).',
            'steps' => ['«Qo\'shish» → ism, telefon, login, parol, mutaxassislik.', 'Guruhga rahbar sifatida «Guruhlar» bo\'limidan biriktiring.'],
        ],
        [
            'key' => 'guardians', 'group' => 'people', 'title' => 'Ota-onalar', 'icon' => 'pi pi-heart', 'route' => '/guardians', 'permission' => 'guardian-index',
            'summary' => 'Ota-ona telefon raqami orqali o\'quvchiga bog\'lanadi va Telegram botdan xabarlar oladi (qarzdorlik, to\'lov, baho, uy ishi, oylik hisobot).',
            'steps' => ['O\'quvchi qo\'shishda «Ota-ona telefoni» kiritilsa avtomatik yaratiladi.', 'Bog\'lash kodi — ota-ona botda /start → telefon yoki kod yuboradi.'],
        ],
        [
            'key' => 'finance', 'group' => 'finance', 'title' => 'To\'lovlar va qarzdorlik', 'icon' => 'pi pi-wallet', 'route' => '/invoices', 'permission' => 'invoice-index',
            'summary' => 'Tarif → oylik hisob-faktura → to\'lov. Balans manfiy bo\'lsa o\'quvchi qarzdor; ota-onaga Telegram eslatmasi avtomatik boradi (5, 15, 25-kunlar).',
            'steps' => ['«Tariflar» da narx yarating → o\'quvchi profilida tarif va chegirma biriktiring.', 'Har oy 1-kuni hisob-fakturalar avtomatik; «To\'lovlar» da to\'lovni kiriting (chek bilan).', '«Qarzdorlar» — ro\'yxat va eslatma yuborish.'],
        ],
        [
            'key' => 'reports', 'group' => 'finance', 'title' => 'Hisobotlar', 'icon' => 'pi pi-chart-bar', 'route' => '/reports', 'permission' => 'report-dashboard',
            'summary' => 'Davomat, baholar, to\'lovlar, qarzdorlar, ko\'nikmalar bo\'yicha o\'zlashtirish, oylik o\'zlashtirish hisobotlari.',
            'steps' => ['Davr va guruhni tanlang.', '«Oylik hisobot» → «Yuborish» — ota-onalarga Telegram orqali tahlil ketadi.'],
        ],
        [
            'key' => 'users', 'group' => 'system', 'title' => 'Foydalanuvchilar, rollar va ruxsatlar', 'icon' => 'pi pi-shield', 'route' => '/roles', 'permission' => 'role-index',
            'summary' => 'Har bir API amali — alohida ruxsat (masalan `student-index`). Rolga ruxsatlar to\'plami beriladi; foydalanuvchiga rol beriladi. Tizim rollari o\'chirilmaydi, yangi rol yaratish mumkin.',
            'steps' => ['«Rollar» → rol → «Ruxsatlar» — kerakli bo\'limlarni belgilang.', '«Foydalanuvchilar» → akkaunt → rolni biriktiring, parolni yangilang, bloklang.', 'Yangi API qo\'shilganda `php artisan permissions:sync` bilan ruxsatlar yangilanadi.'],
            'tips' => ['Superadmin barcha tekshiruvlardan tashqarida.'],
        ],
        [
            'key' => 'notifications', 'group' => 'system', 'title' => 'Telegram bildirishnomalar', 'icon' => 'pi pi-send', 'route' => '/notifications', 'permission' => 'notification_event-index',
            'summary' => 'Ikki bot: ota-onalar (qarzdorlik, hisobot, baho) va o\'quvchilar (variantli testlar). Xabarlar navbatda turadi va har daqiqa yuboriladi.',
            'steps' => ['.env da bot tokenlari → `php artisan telegram:set-webhook`.', 'Navbat sahifasida yuborilgan/xato xabarlarni ko\'ring.'],
        ],
        [
            'key' => 'learn_path', 'group' => 'student', 'title' => 'Dars yo\'li', 'icon' => 'pi pi-map', 'route' => '/learn/path', 'role' => 'student',
            'summary' => 'Har daraja uchun ko\'nikmalar (o\'qish, yozish, gapirish, grammatika, lug\'at). Har dars — 6–10 ta qisqa mashq (juftlash, variant, yozish). Dars tugagach ko\'nikma +20% va XP.',
            'steps' => ['Darajani oching → ko\'nikmani bosing → «Boshlash».', 'Mashqlarni bajaring; xato bo\'lsa to\'g\'ri javob ko\'rsatiladi.', '100% bo\'lgan ko\'nikma yashil belgilanadi.'],
        ],
        [
            'key' => 'learn_tests', 'group' => 'student', 'title' => 'Testlar va uy ishlari', 'icon' => 'pi pi-file-edit', 'route' => '/learn/tests', 'role' => 'student',
            'summary' => 'O\'qituvchi biriktirgan testlar (urinishlar soni cheklangan, mock — 1 marta) va ochiq mashqlar. Insho va gapirish javoblarini o\'qituvchi tekshiradi.',
            'steps' => ['«Boshlash» → bo\'limlar bo\'yicha javob bering → «Topshirish».', 'Natija darhol; o\'qituvchi tekshiruvi kerak bo\'lsa «Kutilmoqda» holati.'],
        ],
        [
            'key' => 'learn_duel', 'group' => 'student', 'title' => 'Duel', 'icon' => 'pi pi-bolt', 'route' => '/learn/duel', 'role' => 'student',
            'summary' => 'Sherigingizni taklif qiling (kod yoki ro\'yxatdan) yoki bot bilan o\'ynang (oson / o\'rta / kuchli). Har raundda kim birinchi to\'g\'ri javob bersa — ochko. G\'olib XP oladi.',
            'steps' => ['«Yangi duel» → sherik tanlang yoki «Bot bilan».', 'Sherik 30 soniyada qo\'shilmasa — bot taklif qilinadi.', 'Raund vaqti 20 soniya; noto\'g\'ri javob bersangiz raundda qulflanasiz.'],
        ],
        [
            'key' => 'learn_vocab', 'group' => 'student', 'title' => 'Lug\'at va so\'z moslashtirish', 'icon' => 'pi pi-language', 'route' => '/learn/vocab', 'role' => 'student',
            'summary' => 'Kartochkalar (old tomoni italyancha, orqasi tanlangan tilda) va so\'z moslashtirish o\'yini; liderbord.',
            'steps' => ['Sozlamalarda tarjima tilini (uz/en/ru) tanlang.', 'O\'yinlarda har to\'g\'ri javob — ball; natijalar liderbordda.'],
        ],
        [
            'key' => 'learn_certificate', 'group' => 'student', 'title' => 'Sertifikat', 'icon' => 'pi pi-verified', 'route' => '/learn/certificate', 'role' => 'student',
            'summary' => 'Oyning 25-sanasidan keyin, oyiga 1 marta. Ball: davomat 40% + uy ishi 30% + test 30%.',
            'steps' => ['«Sertifikat olish» → chop etish / PDF; QR orqali tekshiriladi.'],
        ],
    ],
];
