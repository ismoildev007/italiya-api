# Italiya Akademiyasi — LMS Backend (Laravel 12 + Sanctum)

Italyan tili o'quv markazi uchun to'liq platforma (mockup: `index (20).html`).
CEFR darajalari (A1–C2), IELTS uslubidagi 4 ko'nikma (Listening / Reading / Writing / Speaking),
AI bilan test va darslik generatsiyasi, ovozli lug'at (it ↔ uz/en/ru), so'zlashuv o'yinlari,
uy ishi, Zoom darslar, oylik sertifikat, ikkita Telegram bot va moliya/qarzdorlik.

Har bir markaz **alohida serverga, alohida bazaga** o'rnatiladi (white-label).

---

## Yangilanish (2026-10)

- **Tinglash (audio/TTS/STT) va AI o'chirilgan** — `config/academy.php → features` (`AUDIO_ENABLED`, `AI_ENABLED`). Kod saqlangan, bayroq bilan yoqiladi.
- **Dars yo'li** (`learn/path`) — Duolingo uslubidagi qisqa darslar: darslik + lug'at + mashq testidan avtomatik, XP va ko'nikma progressi.
- **Duel** (`learn/duels`) — WebSocket (Laravel Reverb) orqali "kim birinchi to'g'ri javob beradi"; sherik yoki bot (oson/o'rta/kuchli). Reverb: `php artisan reverb:start` (prod: systemd `italiya-reverb`, nginx `/app` proxy).
- **Qo'llanma** (`GET guide`, frontend `/guide`) — rol/ruxsatga qarab bo'limlar va qadamlar (`config/guide.php`).
- **Online holat** (`learn/presence` heartbeat) — duel sheriklari ro'yxatida online'lar birinchi. **Brauzer push** — Firebase Cloud Messaging (`FIREBASE_PROJECT_ID`, `FIREBASE_CREDENTIALS`; frontend `VITE_FIREBASE_*`): duel taklifi, test, uy ishi va boshqa xabarlar.
- `php artisan academy:reset --force` — demo ma'lumotlarni o'chirib faqat superadminni qoldiradi.

## Tez ishga tushirish

```bash
composer install
cp .env.example .env
php artisan key:generate

# Lokal sinov: DB_CONNECTION=sqlite (touch database/database.sqlite) yoki PostgreSQL/MySQL
php artisan migrate:fresh --seed     # schema + to'liq demo ma'lumotlar
php artisan storage:link             # audio/fayllar uchun
php artisan serve
```

API: `http://127.0.0.1:8000/api/v1`. Barcha javoblar `{ success, message, data, meta }`.
Autentifikatsiya: `Authorization: Bearer <token>`.

### Demo akkauntlar

| Login | Parol | Rol |
|---|---|---|
| `superadmin` | `ismoil_007u` | Superadmin |
| `direktor` | `parol123` | Administrator |
| `buxgalter` | `parol123` | Buxgalter |
| `umida.shodi` | `parol123` | O'qituvchi (Italyan tili) |
| `+998994602923` (yoki `o2026-0001`) | `parol123` | O'quvchi, A1-Ertalab guruhi |
| `ota20000` | `parol123` | Ota-ona |

O'quvchi **telefon raqami + parol** bilan kiradi (`POST login`, `login` = telefon, ixtiyoriy `device_id`).
Birinchi kirishda akkaunt qurilmaga bog'lanadi; boshqa qurilmadan kirish rad etiladi
(admin `DELETE students/{id}/device` bilan tozalaydi).

### Cron (server)

```
* * * * * php /path/artisan schedule:run >> /dev/null 2>&1
```

Jadval (`routes/console.php`): Telegram xabarlari har daqiqa, qarzdorlik eslatmasi (5, 15, 25-kunlar),
oylik hisobot (28-kun), hisob-fakturalar (1-kun).

---

## Sozlamalar (.env)

| Kalit | Vazifasi |
|---|---|
| `AI_GENERATOR` | test/darslik/lug'at generatsiyasi: `gemini` \| `openai` \| `anthropic` |
| `AI_EVALUATOR` | writing/speaking tahlili (Gemini tavsiya etiladi) |
| `OPENAI_API_KEY` | **STT** (ovozni matnga) va **TTS** (listening audio, kartochka talaffuzi) |
| `OPENAI_BASE_URL` | bepul OpenAI-mos API (Groq `https://api.groq.com/openai/v1`, OpenRouter …) — kalit shu provayderniki bo'ladi |
| `GEMINI_API_KEY` | javoblarni tahlil qilish, oylik xulosa, generatsiya |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_WEBHOOK_SECRET` | ota-onalar boti |
| `TELEGRAM_STUDENT_BOT_TOKEN` / `TELEGRAM_STUDENT_WEBHOOK_SECRET` | o'quvchilar boti (test yechish) |
| `ACADEMY_UNIT_MAX_PLAYS` | darslik audiosi necha marta tinglanadi (default 3) |
| `CERTIFICATE_AVAILABLE_FROM_DAY` | sertifikat oyning shu sanasidan keyin (default 25) |
| `ACADEMY_MONTHLY_REPORT_DAY` | oylik tahlil yuboriladigan kun (default 28) |

**Kalit bo'lmasa ham ishlaydi:** tashqi AI sozlanmagan yoki xato bersa, tizim avtomatik lokal "oddiy AI" ga (`App\Services\Ai\LocalAi`) o'tadi — testlar/darsliklar/lug'at tayyor bankdan tuziladi, essay va speaking evristik baholanadi (`provider: local`). Kalit qo'yilishi bilan real AI ishlatiladi, hech narsa o'zgartirish shart emas. STT bo'lmasa brauzer nutqni tanib olishi (`transcript`), TTS bo'lmasa brauzer `speechSynthesis` (`tts_text`) ishlatiladi.

Batafsil: `config/academy.php` (darajalar, ko'nikmalar, yo'nalishlar, limitlar), `config/services.php`.

Webhook: `php artisan telegram:set-webhook https://domen.uz`

---

## Domen modeli

| Tushuncha | Jadval | Izoh |
|---|---|---|
| Guruh | `school_classes` | `level` (A1..C2), `track` (general/medical/farming/special), jadval, Zoom havola. API: `groups` yoki `classes` |
| O'quvchi | `students` | `level`, `track`, `monthly_hours`, `preferred_language` (kartochka orqa tomoni), `telegram_chat_id`, `device_id`, `speech_game_score`, `streak_days` |
| Ota-ona | `guardians` | telefon orqali bog'lanadi (`parent_phone` o'quvchi yaratishda), Telegram `link_code` |
| Darslik | `course_units` | daraja × yo'nalish × ko'nikma; `media_script` → TTS audio; **`max_plays`** — necha marta tinglash mumkin |
| Material | `course_materials` | guruh kutubxonasi: video / kitob / fayl / havola |
| Test | `test_templates` + `test_sections` + `test_questions` | `kind` (practice/homework/test/mock/placement), **`max_attempts`**, **`max_plays`**, `time_limits`, `auto_grade_ai` |
| Urinish | `test_attempts` + `test_answers` | `attempt_no`, `expires_at`, bo'lim taymerlari, `similarity`, `ai_score`, `ai_feedback`, `source` (web/telegram) |
| Eshitish hisobi | `media_plays`, `unit_progress` | o'quvchi × media × (urinish) — limit tugasa **423 Locked** |
| Ko'nikma progressi | `skill_progress` | daraja × ko'nikma foizi (mockupdagi skill barlar) |
| Lug'at | `vocab_decks`, `vocab_cards` | `word` (it) + `translations` `{uz,en,ru}` + misol + TTS audio |
| O'yin | `games`, `game_sessions` | daraja, **savollar soni** (`card_count`), raund turlari, ball; liderbord |
| Uy ishi | `homeworks`, `homework_submissions` | matn/fayl/audio/test; 0..100 ball |
| Zoom | `meetings` (+ `meeting_class`) | guruhlar/darajalar/yo'nalishlar bo'yicha |
| Sertifikat | `certificates` | oyiga 1 marta, 25-sanadan keyin; ball = davomat 40% + uy ishi 30% + test 30% |
| Oylik hisobot | `monthly_reports` | statistika + AI xulosa → Telegram |
| Bot sessiyasi | `telegram_sessions` | suhbat holati |

### Limitlar qoidasi (darslik, test, mock)

`config/academy.php → defaults.kinds`:

| Tur | Urinishlar | Audio eshitish | AI baholash |
|---|---|---|---|
| practice (mashq) | cheksiz | 2 | ha |
| homework | 2 | 2 | ha |
| test | 1 | 1 | ha |
| **mock** | **1** | **1** | ha |
| placement | 1 | 1 | ha |

Har bir shablon, bo'lim va savolda o'z qiymati bilan almashtirish mumkin (`max_attempts`, `max_plays`).
Bo'limlar (reading/writing…) uchun vaqt: `time_limits` yoki `defaults.section_minutes`; urinish umumiy vaqti — `duration_minutes`.
Vaqt tugasa javoblar avtomatik topshiriladi.

---

## AI oqimi

1. **Test generatsiyasi** — `POST tests/generate` `{level, skills[], question_count, kind, topic, difficulty|adaptive}`.
   AI bo'limlar (listening `media_script`, reading `passage`, writing gap+essay, speaking) va savollarni qaytaradi — **bazaga yozilmaydi**.
2. O'qituvchi tahrirlaydi / o'zi tuzadi → `POST tests` (`sections[]`), `POST tests/{id}/audio` (OpenAI TTS listening audiosi), `POST tests/{id}/assign`.
3. O'quvchi yechadi: variantli / true-false / gap / diktant — avtomatik (Levenshtein o'xshashlik, `similarity_threshold`);
   **essay va speaking** — ovoz OpenAI STT bilan matnga o'giriladi, **Gemini** ball (0–100), mezonlar, xatolar va o'zbekcha fikr qaytaradi (`ai_feedback`). O'qituvchi `PUT test-attempts/{id}/grade` bilan o'zgartira oladi.
4. Natija ko'nikma progressiga (`skill_progress`), baholar jurnaliga va ota-onaga xabarga aylanadi.

Darslik: `POST units/generate` → tahrir → `POST units` (`generate_audio: true`). Lug'at: `POST decks/generate`, `POST decks/{id}/import` (Excel/CSV: so'z | tarjima | en | ru | misol | kategoriya), `POST decks/{id}/audio`.

---

## API bo'limlari

| Bo'lim | Endpointlar |
|---|---|
| Auth | `POST login` (telefon/username + parol + `device_id`), `GET me`, `GET/PUT settings` |
| **O'quvchi kabineti** `learn/*` (rol: student) | `dashboard`, `units`, `units/{id}/play`, `tests`, `assignments/{id}/start`, `practice/{id}/start`, `attempts/{id}`, `attempts/{id}/sections/{s}/open|play`, `attempts/{id}/questions/{q}/play`, `attempts/{id}/answer` (audio bilan), `attempts/{id}/submit`, `results`, `decks`, `decks/{id}/cards?lang=uz`, `games`, `games/{id}/start`, `game-sessions/{id}/answer|finish`, `leaderboard`, `homeworks`, `homeworks/{id}/submit`, `meetings`, `certificate/eligibility`, `POST certificate`, `certificates`, `monthly-report` |
| Guruhlar | `groups` (= `classes`), `groups/{id}/students`, `.../attendance/journal` |
| O'quvchilar | `students`, `students/{id}/level`, `level-history`, `DELETE device`, `certificate-eligibility`, `transfer`, `finance` |
| Darsliklar | `units`, `units/generate`, `units/{id}/audio`, `materials` |
| Testlar | `tests`, `tests/generate`, `tests/{id}/audio|publish|assign`, `test-assignments`, `test-attempts/{id}`, `.../grade` |
| Lug'at / o'yinlar | `decks`, `decks/generate`, `decks/{id}/import|audio`, `games`, `games/{id}/results|leaderboard`, `leaderboard` |
| Uy ishi | `homeworks`, `homeworks/{id}/submissions`, `homework-submissions/{id}/grade` |
| Zoom | `meetings` |
| Sertifikat | `certificates` (admin), `certificates/verify/{code}` va `certificates/{serial}/html` (ochiq) |
| Hisobotlar | `reports/dashboard|attendance|grades|payments|debtors|salaries|skills|monthly-progress`, `POST reports/monthly-progress/send` |
| Moliya | `tariffs`, `invoices`, `payments`, `finance/debtors` |
| Telegram | `POST telegram/student/{secret}`, `POST telegram/parent/{secret}` (webhook), `notifications/*` |

To'liq ro'yxat: `php artisan route:list --path=api`. Yangi route qo'shgach: `php artisan permissions:sync`.

### Mockup → API xaritasi

| Mockup bo'limi | Backend |
|---|---|
| Bosh sahifa (statistika, streak) | `learn/dashboard`, `reports/dashboard` |
| O'quvchilar bazasi (login, qulf, qurilma) | `students`, `users/{id}/status`, `students/{id}/device` |
| Darajalar A1–C2 (video/fayl/uy ishi paneli, skill barlar) | `units`, `materials`, `homeworks`, `learn/dashboard.skills` |
| Tinglash / Yozish / Gapirish / O'qish modallari | `learn/tests` → attempt oqimi (bo'limlar, taymer, audio limiti, AI baho) |
| Ovozli lug'at + Excel import | `decks`, `decks/{id}/import`, `learn/decks/{id}/cards?lang=` |
| So'zlashuv o'yini + liderbord | `games`, `learn/games/*`, `learn/leaderboard` |
| Interaktiv testlar (Excel'dan) | `decks/{id}/import` + `games` (`choice`) yoki `tests` |
| Sertifikat generator | `learn/certificate` (25-sanadan keyin, oyiga 1), `certificates/{serial}/html` |
| Zoom mitinglar | `meetings`, `learn/meetings` |
| Sozlamalar (bildirishnoma, ovoz, mavzu, til) | `settings` |
| Meditsina / Fermerlik / Grant guruhlari | `track` = medical / farming / special |

---

## Telegram botlar

**O'quvchilar boti** (faqat variantli testlar): `/start` → telefon → parol → `/tests` → test tanlash →
savollar inline tugmalar bilan → natija. Urinishlar limiti platformadagi bilan bir xil (`source = telegram`).
`/results`, `/level`, `/logout`.

**Ota-onalar boti**: telefon yoki `link_code` bilan bog'lanish; `/debt` (qarzdorlik), `/report` (oylik tahlil), `/children`.
Avtomatik xabarlar (`notification_events` → `notifications:send`): qarzdorlik eslatmasi, to'lov, baho, davomat,
uy ishi berildi/baholandi, test biriktirildi, sertifikat, daraja o'zgardi, **oylik o'zlashtirish tahlili**.

Buyruqlar: `notifications:send`, `finance:debt-reminders`, `reports:monthly --year --month`, `telegram:set-webhook`.

---

## Struktura

```
app/
  Services/
    Ai/                 AiClient interfeysi, GeminiClient, OpenAiClient, AnthropicClient, AiManager, SpeechService (STT/TTS)
    Telegram/           TelegramClient, TelegramNotifier, StudentBotService, ParentBotService
    AiTestGeneratorService, AiEvaluationService, TestService (bo'limlar, limitlar, taymer, baholash)
    CourseUnitService, VocabService, GameService, HomeworkService, MeetingService
    LearningService (o'quvchi kabineti), MediaPlayService, SkillProgressService
    CertificateService, MonthlyReportService, StudentStatsService, DebtReminderService
  Support/              Level (CEFR), TextSimilarity (Levenshtein), SpreadsheetReader (CSV/XLSX)
config/academy.php      darajalar, ko'nikmalar, yo'nalishlar, limitlar, sertifikat qoidalari
database/seeders/Data/  ItalianContent (A1–C2 kontent), TestBank (IELTS uslubidagi shablonlar)
resources/views/certificates/show.blade.php   chop etiladigan sertifikat
tests/Feature/          81 test (auth/qurilma, limitlar, AI baholash, sertifikat, o'yin, bot, uy ishi…)
```

Qat'iy qoida saqlanadi: kontrollerda faqat metod chaqiruvi, validatsiya FormRequest'da, biznes-logika servisda.

---

## Testlar

```bash
php artisan test
```

---

## Keyingi bosqichlar

- Frontend: `index (20).html` mockupini API ga ulash (Vue/React) — "Mockup → API xaritasi" bo'limiga qarang
- Flutter mobil ilova (o'quvchi va ota-ona)
- Placement (daraja aniqlash) testini ro'yxatdan o'tishga ulash
