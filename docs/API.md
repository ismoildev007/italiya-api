# Italiya Akademiyasi — API shartnomasi (frontend uchun)

Backend: `../italiya` (Laravel 12). Base URL: `{VITE_BACKEND_URL}/api/v1`.
Demo server: `php artisan serve` → `http://127.0.0.1:8000`. Demo baza: `php artisan migrate:fresh --seed`.

Barcha javoblar bir xil konvert:

```json
{ "success": true, "message": "…", "data": …, "meta": { "current_page", "per_page", "total", "last_page" } }
```

`meta` faqat sahifalangan ro'yxatlarda. Xato: `{ "success": false, "message": "…", "errors": {field: [..]} }`.
Muhim HTTP kodlar: `401` token yo'q, `403` ruxsat yo'q, `422` validatsiya / biznes qoida (urinish tugadi, sertifikat hali emas…),
**`423` — audio eshitish limiti tugagan**, `500` — server xatosi (`message` toza matn, SQL ko'rsatilmaydi). Noto'g'ri id (`/students/undefined`) → `404`.

**AI hech qachon xato qaytarmaydi:** tashqi kalit (Gemini/OpenAI) bo'lmasa yoki ishlamasa, backend avtomatik lokal "oddiy AI" ga o'tadi (`provider: "local"`): testlar/darsliklar/lug'at tayyor bankdan tuziladi, essay/speaking evristik baholanadi. `GET ai/status` → `fallback`.

Autentifikatsiya: `Authorization: Bearer <token>` (Sanctum). Fayl yuborish — `multipart/form-data`.

---

## 1. Auth va profil

| Method | URL | Body | Izoh |
|---|---|---|---|
| POST | `login` | `{login, password, device_id?, device?}` | `login` = username **yoki telefon** (`+998 90 123-45-67` ham bo'ladi). O'quvchi uchun `device_id` (localStorage UUID) yuboring — birinchi kirishda bog'lanadi, boshqa qurilma → 422 `errors.device_id` |
| GET | `me` | | `data.user`, `data.permissions` (`["*"]` superadmin), `data.school` (`name, short_name, logo, phone, address, currency, website, director, telegram` — oxirgi uchtasi bo'sh bo'lsa `null`), **`data.academy`** (`levels`, `skills`, `tracks`, `translation_languages`), `data.student` (student bo'lsa) |
| POST | `logout`, `logout-all`, `change-password` | | |
| GET/PUT | `settings` | `{notifications, sound, theme: auto\|light\|dark, ui_language, preferred_language}` | mockup "Sozlamalar" modali. `preferred_language` — kartochka orqa tomoni tili (uz/en/ru) |

Login javobi (o'quvchi):

```json
{ "access_token": "1|…", "token_type": "Bearer",
  "user": { "id": 12, "full_name": "…", "username": "o2026-0001", "phone": "+998994602923", "settings": {"notifications":true,"sound":true,"theme":"auto","ui_language":"uz"}, "role_names": ["student"] },
  "permissions": ["portal-…"],
  "student": { "id": 1, "full_name": "…", "level": "A1", "level_title": "Boshlang'ich (A1)", "track": "general", "group": "A1-Ertalab", "group_id": 1, "preferred_language": "uz" } }
```

Rollar: `superadmin`, `admin`, `class_teacher` (guruh rahbari), `teacher`, `accountant`, `parent`, `student`.
Admin panel route'lari `permissions` ro'yxatiga qarab (`<controller>-<action>`: `course_unit-index`, `vocab_deck-store`, `game-index`, `homework-index`, `meeting-index`, `certificate-index`, `report-skills` …).
O'quvchi interfeysi `role_names` ichida `student` bo'lsa ochiladi — `learn/*` endpointlari permission talab qilmaydi.

### Lug'atlar (`GET me` → `data.academy`)

```json
"levels": { "A1": {"ordinal":1,"title":"Boshlang'ich (A1)","description":"…"}, … "C2": {…} },
"skills": { "listening":"Tinglash (Ascolto)", "reading":"O'qish (Lettura)", "writing":"Yozish (Scrittura)", "speaking":"Gapirish (Parlato)", "grammar":"Grammatika (Grammatica)", "vocabulary":"Lug'at (Lessico)" },
"tracks": { "general":"Umumiy italyan tili", "medical":"Tibbiyot xodimlari uchun", "farming":"Fermer / agrar yo'nalish", "special":"Maxsus (biznes, universitet, viza)" },
"translation_languages": { "uz":"O'zbekcha", "en":"English", "ru":"Русский" }
```

Test turlari (`kind`): `practice` (mashq, cheksiz urinish), `homework`, `test`, `mock` (1 urinish, audio 1 marta), `placement`.
Savol turlari (`type`): `single_choice`, `multiple_choice`, `true_false` (javob `true|false|ng`), `short_text`, `gap`, `dictation`, `essay`, `speaking` (ovozli).

---

## 2. O'quvchi kabineti — `learn/*` (rol: student)

### 2.1 Bosh sahifa — `GET learn/dashboard`

```json
{ "student": { "id":1, "full_name":"…", "photo":null, "level":"A1", "level_title":"…", "next_level":"A2", "track":"general", "track_title":"…", "group":"A1-Ertalab", "preferred_language":"uz", "streak_days":0, "speech_game_score":150, "monthly_hours":23 },
  "skills": { "listening": {"label":"Tinglash (Ascolto)","percent":100,"points":60,"attempts":2}, "reading": {…}, "writing": {…}, "speaking": {…}, "grammar": {…}, "vocabulary": {…} },
  "overall_percent": 83.53, "pass_percent": 60,
  "pending": { "tests": 2, "homeworks": 0 },
  "upcoming_meetings": [ {"id":1,"title":"…","starts_at":"2026-09-21 19:00:00","link":"https://zoom.us/j/…"} ],
  "certificate": { "eligible": false, "reason": "Sertifikat oyning 25-sanasidan keyin olinadi.", "available_from": "2026-09-25", "period": "2026-09" },
  "leaderboard": [ {"rank":1,"student_id":1,"name":"…","level":"A1","score":150} ],
  "recent_results": [ {"attempt_id":1,"title":"…","kind":"mock","percent":79.84,"status":"graded","date":"…"} ] }
```

`skills.*.percent` — mockupdagi skill barlar. `overall_percent` — 4 asosiy ko'nikma o'rtachasi.

### 2.2 Darsliklar

| Method | URL | Izoh |
|---|---|---|
| GET | `learn/units?level=&skill=` | o'z darajasi + yo'nalishi bo'yicha (`general` ham). Har birida `has_media`, `effective_max_plays`, `play_state {plays, limit, remaining, locked}` (media bo'lsa), `progress {plays, views, completed_at, score}`, `practice_test` (mashq test shabloni) |
| GET | `learn/units/{id}` | `content` — markdown; `media_url` — audio/video; `materials[]` |
| POST | `learn/units/{id}/play` | **har audio/video ochilishida chaqiring** → `{plays, limit, remaining, locked}`; limit tugasa **423** — pleyerni bloklang |
| POST | `learn/units/{id}/complete` | darsni yakunlash |

### 2.3 Testlar (IELTS oqimi)

`GET learn/tests?kind=&skill=` →

```json
{ "assigned": [ { "assignment_id":5, "template_id":3, "title":"…", "kind":"homework", "level":"A1", "skills":["writing","speaking"], "question_count":7, "duration_minutes":45, "max_plays":2, "pass_percent":60, "starts_at":"…", "due_at":"…", "teacher":"…", "completed":true, "best_percent":50,
      "state": { "used":1, "limit":2, "remaining":1, "can_start":true, "in_progress_attempt_id":null } } ],
  "practice": [ { "template_id":2, "title":"…", "level":"A1", "skills":["listening","reading"], "question_count":8, "duration_minutes":40, "course_unit_id":null } ] }
```

`state.limit = null` — cheksiz. `in_progress_attempt_id` bo'lsa "Davom etish" tugmasi.

| Method | URL | Body | Izoh |
|---|---|---|---|
| POST | `learn/assignments/{assignment}/start` | | biriktirilgan test. Urinish tugagan → 422 |
| POST | `learn/practice/{template}/start` | | ochiq mashq |
| GET | `learn/attempts/{attempt}` | | joriy holat (qayta yuklashda) |
| POST | `learn/attempts/{a}/sections/{section}/open` | | bo'limga kirganda — bo'lim taymeri boshlanadi → `{skill, started_at, expires_at}` |
| POST | `learn/attempts/{a}/sections/{section}/play` | | bo'lim audiosi (listening) — `{plays, limit, remaining, locked}` / 423 |
| POST | `learn/attempts/{a}/questions/{question}/play` | | savol audiosi bo'lsa |
| POST | `learn/attempts/{a}/answer` | `{question_id, selected_option_ids?[], answer_text?, transcript?}` yoki multipart `audio` (webm/mp3/m4a) | **autosave** — har javobda chaqiring. Ovozli javobda serverda OpenAI STT → `data.transcript` |
| POST | `learn/attempts/{a}/submit` | `{answers?: [{question_id, …}]}` | topshirish; natija darhol qaytadi |
| GET | `learn/results?kind=` | | barcha natijalar |

`start` / `attempt` / `submit` javobi (attempt view):

```json
{ "attempt": { "id":327, "attempt_no":3, "status":"in_progress|submitted|graded|expired", "started_at":"ISO", "expires_at":"ISO", "seconds_left":2399, "submitted_at":null, "total_score":0, "max_score":8, "percent":0, "passed":null,
               "summary": { "by_skill": { "listening": {"score":2,"max":3,"percent":66.67,"correct":2,"total":3} }, "ai_feedback": [ {"question_id":9,"feedback":"…"} ] } },
  "test": { "id":2, "title":"…", "kind":"practice", "level":"A1", "duration_minutes":40, "max_plays":2, "show_answers":true },
  "sections": [ { "id":5, "skill":"listening", "skill_label":"…", "title":"Ascolto", "instructions":"…", "passage": null, "media_url": "http://…/storage/tests/2/sections/x.mp3", "has_media": true,
                  "play_state": {"plays":0,"limit":2,"remaining":2,"locked":false}, "time_limit_minutes":15, "started_at":null, "expires_at":null,
                  "questions": [ { "id":17, "type":"single_choice", "skill":"listening", "answer_mode":"written|voice", "body":"Dove abita Marco?", "translation":"…(uz)", "score":1, "media_url":null, "play_state":null, "meta":{"min_words":60},
                                   "options":[{"id":13,"body":"A Bologna"}],
                                   "answer": null | {"selected_option_ids":[13],"answer_text":null,"transcript":null,"audio_url":null,"is_correct":true,"score":1,"similarity":null,"ai_feedback":{…},"feedback":null},
                                   "correct_answer":null, "model_answer":null, "explanation":null } ] } ] }
```

- `seconds_left` — umumiy taymer; bo'lim taymeri `sections[].expires_at` (`open` dan keyin). Vaqt tugasa server javoblarni avtomatik topshiradi (422 keladi → `GET attempts/{id}` bilan natijani oling).
- Topshirilgandan keyin `answer.is_correct/score/ai_feedback` to'ldiriladi; `show_answers=true` bo'lsa `correct_answer`, `model_answer`, `explanation`, `options[].is_correct` ham keladi.
- `ai_feedback` (essay/speaking): `{score_percent, criteria:{grammar,vocabulary,…}, errors:[{wrong,correct,note}], corrected_text, feedback, transcript}`.
- `reading` bo'limida `passage` — matn; `true_false` javob: `answer_text: "true"|"false"|"ng"`; `gap`/`dictation`/`short_text`: `answer_text`; `speaking`: `audio` fayl (yoki brauzer SpeechRecognition natijasi `transcript`).

### 2.4 Ovozli lug'at va o'yinlar

| Method | URL | Izoh |
|---|---|---|
| GET | `learn/decks?level=&category=` | o'z darajasi va pastidagi to'plamlar |
| GET | `learn/decks/{deck}/cards?lang=uz&shuffle=1` | `{deck, language, cards:[{id, front:"Arrivederci", front_language:"it", back:"Goodbye", back_language:"en", translations:{uz,en,ru}, category, example, example_translation, pronunciation, audio_url}]}` |
| GET | `learn/games?level=` | `[{id,title,type,type_label,level,card_count,seconds_per_card,round_types,points_per_correct,deck,my_sessions_count,my_best_score}]` |
| POST | `learn/games/{game}/start` | sessiya: `{id, total_rounds, rounds:[…], game}` — **`rounds[].answer` yashirin** |
| POST | `learn/game-sessions/{s}/answer` | `{round_no, answer}` yoki multipart `audio` (voice raund) → `{correct, expected, given, similarity, points, score, remaining}`. Xato bo'lsa raundni keyinroq qayta ko'rsating (mockup spaced-repetition) |
| POST | `learn/game-sessions/{s}/finish` | yakuniy `{score, correct, wrong, duration_seconds, rounds (javoblar bilan), details}`; ball `speech_game_score` ga qo'shiladi |
| GET | `learn/leaderboard/{game?}?level=&limit=` | `[{rank, student_id, name, level, score}]` |

Raund turlari: `it-to-tr` (`prompt` italyancha, `options[]` tarjimalar), `tr-to-it` (`prompt` tarjima, `options[]` italyancha), `voice` (`prompt` — talaffuz qilinadigan so'z, `hint`, `audio_url`), `typing` (yozish), `listening` (`audio_url`/`tts_text` eshitib yozish). Har raundda `card_id`, `no`, `answer_language`.

### 2.5 Uy ishi, Zoom, sertifikat, hisobot

| Method | URL | Izoh |
|---|---|---|
| GET | `learn/homeworks?status=active` | `[{id,title,description,level,attachment_url,test_template_id,course_unit_id,due_at,is_overdue,max_score,status,teacher,test,unit, my_submission: null|{status:"pending|checked|returned",score,feedback,file_url,audio_url,submitted_at}}]` |
| POST | `learn/homeworks/{hw}/submit` | multipart: `text?`, `file?`, `audio?`, `test_attempt_id?` (test bo'lsa avval attempt yeching). Tekshirilgan bo'lsa 422 |
| GET | `learn/meetings?all=1` | kelgusi Zoom darslar: `{id,title,platform,link,starts_at,duration_minutes,levels,tracks,status,teacher,groups}` |
| GET | `learn/certificate/eligibility` | `{eligible, reason, available_from, period, already_issued: null|Certificate, preview:{attendance,homework,test,overall,skills,level}}` |
| POST | `learn/certificate` | sertifikat yaratish (25-sanadan keyin, oyiga 1) → Certificate; 422 bo'lsa `message` ni ko'rsating |
| GET | `learn/certificates` | `[{id, serial:"ITA-202608-0001", verify_code, level, level_title, period:"2026-08", title, attendance_score, homework_score, test_score, overall_score, grade_label:"Sufficiente|Buono|Ottimo|Eccellente", skills:{…}, issued_at, html_url, verify_url}]` — `html_url` ni yangi oynada oching (chop etish/PDF) |
| GET | `learn/monthly-report?year=&month=` | `{year, month, data:{attendance:{percent,total,present,absent}, tests:{count,average_percent,best_percent,mocks,items[]}, homework:{count,average_percent}, games:{sessions,score,accuracy}, units_completed, skills:{…}, scores:{attendance,homework,test,overall}}, summary}` |

Ommaviy (token'siz): `GET certificates/verify/{code}` → `{valid, certificate}`; `GET certificates/{serial}/html` → chop etiladigan sahifa.

---

## 3. Admin / o'qituvchi paneli

Barcha ro'yxatlar: `?search=&per_page=&page=` + filtrlar. Guruhlar uchun `groups` va `classes` bir xil.

### 3.1 Guruhlar — `groups` (CRUD), `groups/{id}/students`, `groups/{id}/attendance/journal`

Maydonlar: `name`, **`level`** (A1..C2, majburiy), `track`, `schedule_days:[1..7]`, `start_time "09:00"`, `end_time`, `class_teacher_id`, `room`, `zoom_link`, `starts_on`, `description`, `capacity`, `is_active`. Filtr: `level`, `track`, `class_teacher_id`, `is_active`.

### 3.2 O'quvchilar — `students`

Qo'shimcha maydonlar: `level`, `track`, `monthly_hours`, `preferred_language`, **`parent_phone`** (+ `parent_firstname`, `parent_lastname`, `parent_relation: father|mother|grandparent|other`) — ota-ona avtomatik yaratiladi/bog'lanadi, `create_account`, `password`.
Resursda: `level_title`, `track_title`, `telegram_linked`, `device_bound`, `speech_game_score`, `streak_days`, `group`, `skills[]` (show), `guardians[]`.

| Method | URL | Body |
|---|---|---|
| PUT | `students/{id}/level` | `{level, reason: placement\|promoted\|manual\|certificate\|demoted}` |
| GET | `students/{id}/level-history` | |
| DELETE | `students/{id}/device` | qurilma bog'lanishini tozalash |
| GET | `students/{id}/certificate-eligibility` | |
| Filtr | `students?level=&track=&class_id=&debtors=1` | |

### 3.3 Darsliklar — `units`, `materials`

| Method | URL | Body |
|---|---|---|
| POST | `units/generate` | `{level, skill, track?, topic?, translation_language?}` → `{title, description, content(md), media_script, vocabulary[], practice_questions[], max_plays, generated_by:"ai"}` — **saqlanmaydi**, tahrirlab `POST units` ga yuboring |
| GET/POST/PUT/DELETE | `units` | `{level, skill, track, title, description, content, media_script, media_url, media (fayl), max_plays, time_limit_minutes, test_template_id, is_published, generate_audio}` |
| POST | `units/{id}/audio?force=1` | `media_script` dan TTS audio |
| GET | `materials?class_id=&level=&track=&course_unit_id=&type=` | guruh kutubxonasi |
| POST | `materials` | multipart `{file \| url, type: video\|audio\|file\|book\|link, title, class_id?, level?, track?, course_unit_id?, max_plays?}` |
| DELETE | `materials/{id}` | |

### 3.4 Testlar — `tests`

| Method | URL | Body |
|---|---|---|
| POST | `tests/generate` | `{level, skills:["listening","reading","writing","speaking"], question_count (har bo'lim), kind?, topic?, difficulty: easy\|medium\|hard\|adaptive, student_id?, class_id?, translation_language?, instructions?}` → `{title, description, level, track, kind, sections:[…], difficulty, prompt, model, provider}` — saqlanmaydi. AI kaliti yo'q → 503 |
| POST/PUT | `tests`, `tests/{id}` | `{title, level, kind, track?, topic?, description?, duration_minutes, max_attempts?, max_plays?, auto_grade_ai, show_answers, pass_percent, time_limits?:{reading:20}, course_unit_id?, is_published, generate_audio?, sections:[{id?, skill, title, instructions, passage, media_script, media_url, max_plays, time_limit_minutes, questions:[{id?, body, type, answer_mode, score, correct_answer, model_answer, explanation, media_script, max_plays, similarity_threshold, translations:{uz:"…"}, meta, options:[{body,is_correct}]}]}]}` |
| GET | `tests/{id}` | shablon + `sections[].questions[].options[]` (to'g'ri javoblar bilan), `effective_max_attempts`, `effective_max_plays`, `skills[]` |
| POST | `tests/{id}/audio?force=1` | listening audio (TTS) |
| POST | `tests/{id}/publish` | `{published: true\|false}` |
| POST | `tests/{id}/assign` | `{class_id \| student_id, starts_at?, due_at?}` |
| GET | `test-assignments?class_id=&student_id=&kind=&level=&status=` | |
| GET | `test-assignments/{id}/results` | `{assignment, summary:{attempts, students, average_percent, best_percent, graded, awaiting_review, passed}, attempts:[…]}` |
| GET | `test-attempts/{id}` | urinish: `answers[]` — `answer_text`, `transcript`, `audio_url`, `similarity`, `is_correct`, `score`, `ai_score`, `ai_feedback`, `feedback`, `question` |
| PUT | `test-attempts/{id}/grade` | `{scores:[{answer_id, score, feedback?, is_correct?}]}` |
| Filtr | `tests?level=&kind=&skill=&track=&is_published=&generated_by=` | |

### 3.5 Lug'at — `decks`; O'yinlar — `games`

| Method | URL | Body |
|---|---|---|
| POST | `decks/generate` | `{level, topic?, count?, track?, languages?:["uz","en"]}` → `{cards:[{word, part_of_speech, translations:{uz,en,ru}, example, example_translations, pronunciation}]}` |
| POST/PUT | `decks` | `{title, level, track?, category?, description?, is_published, cards:[{id?, word, translations:{uz,en,ru}, example, example_translations, category, part_of_speech, pronunciation}]}` |
| POST | `decks/import-bundle` | multipart `{file (xlsx/csv/tsv), level, track?, title?, category?, course_unit_id?, create_game?=1, create_test?=1, game_type?=choice, card_count?, question_count?, is_published?=1}` → `201 {deck, game\|null, test\|null, imported}`. **Testlar va so'z o'yinlari bitta shu Excel orqali kiritiladi**: `[sarlavha qatorlari]` → `№ \| Italiya so'zi \| O'zbekcha tarjimasi \| Kategoriya` (`italyan_50_soz.xlsx` namunasi; sarlavha qatori avtomatik topiladi, `№` tashlanadi, ixtiyoriy `en`/`ru`/`misol` ustunlari). Natija: lug'at to'plami + `choice` o'yini (so'zlar ≥ 3 bo'lsa) + `practice` test (`single_choice`, 4 variant, it→uz / uz→it navbat bilan; `course_unit_id` berilsa darsga mashq testi sifatida biriktiriladi) |
| POST | `decks/{id}/import` | multipart `file` (csv/tsv/xlsx) — yuqoridagi bilan bir xil format (sarlavha qatorlari va `№` ustuni avtomatik aniqlanadi); mavjud to'plamga so'z qo'shadi |
| POST | `decks/{id}/audio` | kartochka talaffuzi (TTS) |
| POST/PUT | `games` | `{title, type: flashcard\|match\|choice\|speech\|listening\|typing, level, track?, vocab_deck_id?, card_count, seconds_per_card, round_types?:["it-to-tr","tr-to-it","voice","typing","listening"], answer_language?, points_per_correct, is_active}` |
| GET | `games/{id}/results`, `games/{id}/leaderboard`, `leaderboard?level=&class_id=` | |

### 3.6 Uy ishi — `homeworks`

`{title, class_id \| student_id, level?, description?, attachment (fayl), test_template_id?, course_unit_id?, due_at, max_score, status}`.
`GET homeworks/{id}` → `submissions[]`; `GET homeworks/{id}/submissions`; `PUT homework-submissions/{id}/grade` `{score, feedback?, returned?}`.
Ro'yxatda `submissions_count`, `pending_count`.

### 3.7 Zoom — `meetings`

`{title, link, starts_at, duration_minutes, platform, description?, class_ids?:[], levels?:["A1"], tracks?:["medical"], status}`; filtr `upcoming=1&class_id=&from=&to=`.

### 3.8 Sertifikatlar va hisobotlar

| Method | URL | Izoh |
|---|---|---|
| GET | `certificates?student_id=&class_id=&level=&period_year=&period_month=` | |
| POST | `certificates` | `{student_id, force?}` — admin beradi (`force` 25-sana qoidasini chetlab o'tadi) |
| GET | `reports/skills?level=&class_id=&track=` | `{by_skill:[{skill,label,average,students}], by_level:[…], students_needing_attention:[…], total_students}` |
| GET | `reports/monthly-progress?year=&month=&student_id=&class_id=` | oylik hisobotlar (ko'rish) |
| POST | `reports/monthly-progress/send` | `{year?, month?, student_id?, with_ai?}` → Telegram'ga yuborish |
| GET | `reports/dashboard\|attendance\|grades\|payments\|debtors\|salaries` | avvalgidek |
| GET | `ai/status` | `{generator:{provider,model,configured}, evaluator, stt, tts}` — "Sozlamalar" sahifasida ko'rsating |
| GET | `notifications?status=&type=` | Telegram navbati; `type`: `debt_reminder, payment_received, grade_created, attendance_marked, test_assigned, homework_assigned, homework_graded, monthly_report, certificate_issued, meeting_scheduled, level_changed` |

---

## 4. Mockup tablari → sahifalar

| Mockup | Rol | Endpointlar |
|---|---|---|
| Bosh sahifa | student | `learn/dashboard` |
| | admin | `reports/dashboard`, `reports/skills` |
| O'quvchilar bazasi | admin | `students` (+ `level`, `track`, `parent_phone`, `device`), `users/{id}/status` (bloklash) |
| Darajalar A1–C2 (3 ustun: video / fayl / uy ishi) | admin | `materials?level=` / `materials?class_id=`, `homeworks`, `units?level=` |
| | student | `learn/units`, `learn/homeworks` |
| Listening / Reading / Writing / Speaking modallar | student | `learn/tests` → attempt oqimi (2.3) |
| Ovozli lug'at (+ Excel) | admin | `decks`, `decks/import-bundle` (Excel → to'plam + o'yin + test), `decks/{id}/import` |
| | student | `learn/decks/{id}/cards?lang=` |
| So'zlashuv o'yini + liderbord | admin | `games` |
| | student | `learn/games`, `learn/game-sessions/*`, `learn/leaderboard` |
| Interaktiv testlar | admin | `tests` (AI generatsiya → tahrir → assign) |
| Sertifikat generator | student | `learn/certificate/eligibility`, `learn/certificate`, `html_url` |
| | admin | `certificates`, `POST certificates {force}` |
| Zoom mitinglar | admin | `meetings` |
| | student | `learn/meetings` |
| Grant / Meditsina / Fermerlik | — | `track = special / medical / farming` (guruh, darslik, lug'at filtrlari) |
| Sozlamalar modali | hamma | `settings` |

## 5. Ovoz va audio (brauzer tomoni)

- **Speaking / voice raund:** `MediaRecorder` (webm/opus) → `audio` maydoni bilan multipart yuboring; server OpenAI STT → `transcript`. Brauzer `SpeechRecognition` (it-IT) natijasini `transcript` sifatida ham yuborish mumkin (STT o'rniga).
- **Listening:** `media_url` bo'lsa `<audio>`; bo'lmasa `tts_text` (bo'lim/savol/darslikda) — brauzer `speechSynthesis` (it-IT) bilan o'qing. Har ikkalasida ham ochishdan **oldin** `…/play` ni chaqiring; `locked=true` yoki 423 → pleyerni o'chiring, `remaining` ni ko'rsating.
- **Kartochka TTS:** `audio_url` bo'lsa uni, bo'lmasa brauzer `speechSynthesis` (`it-IT` / `uz-UZ`).
- Taymer: `seconds_left` va bo'lim `expires_at` dan hisoblang; 0 bo'lganda `submit` chaqiring.

## 6. Demo akkauntlar

| Login | Parol | Rol |
|---|---|---|
| `superadmin` | `ismoil_007u` | superadmin |
| `direktor` | `parol123` | admin |
| `umida.shodi` | `parol123` | o'qituvchi |
| `+998994602923` | `parol123` | o'quvchi (A1-Ertalab) |
| `ota20000` | `parol123` | ota-ona |

---

## 7. Yangilanish (2026-10-14): tinglash o'chirildi, AI o'chirildi, Dars yo'li, Duel, Qo'llanma

**Funksiya bayroqlari** (`GET me` → `data.academy.features`): `{ ai: false, audio: false, duel: true }`.
- `audio=false`: `listening` ko'nikmasi, `dictation` savol turi, `voice`/`listening` raund turlari qabul qilinmaydi (422); `…/audio` endpointlari 422; `tts_text` kelmaydi; `learn/units/{id}/play` 422.
- `ai=false`: `tests/generate`, `units/generate`, `decks/generate` → 422; insho/gapirish javoblari o'qituvchi tekshiruviga tushadi (`status: submitted`). `GET ai/status` → `enabled`, `audio_enabled`.
- Yoqish: `.env` `AI_ENABLED=true`, `AUDIO_ENABLED=true`.

**XP**: `students.xp` (`GET me` → `student.xp`; `learn/dashboard`, `learn/path`). Manbalar: `config/academy.php → xp`.

### Dars yo'li — `learn/path`

| Method | URL | Javob |
|---|---|---|
| GET | `learn/path` | `{current_level, xp, streak_days, duel_wins, levels:[{level,title,unlocked,is_current, skills:[{skill,label,percent,lessons_done,done}]}]}` |
| GET | `learn/path/{level}/{skill}` | intro: `{title, difficulty: easy\|medium\|hard, exercise_count, minutes, xp, unit}` |
| POST | `learn/path/{level}/{skill}/start` | sessiya: `{id, total, exercises:[…]}`; daraja ochilmagan → 403 |
| GET | `learn/lessons/{id}` | joriy holat |
| POST | `learn/lessons/{id}/answer` | `{no, answer}` → `{correct, expected, xp, progress}` |
| POST | `learn/lessons/{id}/finish` | `{correct, total, accuracy, xp_earned, xp_total, skill_percent}` — ko'nikma +20% × aniqlik |

Mashq turlari: `match` (`left:[{id,text}]`, `right:[{id,text}]`; javob `{leftId: rightId}`), `choice` (`options[]`, `labels?`, `passage?`), `gap` / `typing` (matn, o'xshashlik ≥0.85), `speak` (`model_answer`, javob `"ok"`).

### Duel — `learn/duels` (WebSocket + polling)

| Method | URL | Izoh |
|---|---|---|
| GET | `learn/duels` | `{active[], history[], invites[], stats:{wins,losses,xp}, bots:[{key,title,accuracy}]}` |
| GET | `learn/duels/opponents?q=` | o'z darajasidagi o'quvchilar `{id,name,level,xp,wins,online}` |
| POST | `learn/duels` | `{opponent_student_id?, level?}` → state (`waiting`); taklif `student.{id}` kanaliga `duel.invited` |
| POST | `learn/duels/join` / `learn/duels/{id}/join` | `{code}` yoki id bilan qo'shilish → `active` |
| POST | `learn/duels/{id}/bot` | `{bot_level: easy\|medium\|hard}` — sherik topilmasa bot |
| GET | `learn/duels/{id}` | state: `{status, you: host\|opponent, host, opponent{is_bot}, total, current_no, seconds_left, question{no,body,options}, you_answered, round{winner,host_answered,opponent_answered}, rounds[], host_score, opponent_score, winner, xp}` |
| POST | `learn/duels/{id}/answer` | `{no, answer}` → `{correct, won_round, expected, state}`; raundda bitta javob, noto'g'ri → qulf |
| POST | `learn/duels/{id}/cancel` | |

Qoidalar: 7 savol (lug'at + testlardagi variantli), raund 20 s, birinchi to'g'ri javob — ochko; bot kechikish/aniqlik `config/academy.php → duel_bots`. XP: g'alaba 20, durang 8, raund 3.
WebSocket: Laravel Reverb, kanal `private-duel.{id}` hodisa `.duel.updated`, `private-student.{id}` hodisa `.duel.invited`; auth `POST /api/broadcasting/auth` (Bearer). Frontend `src/stores/echo.js`; ulanmasa 2 s polling.

### Qo'llanma — `GET guide`
`{groups:[{key,title,icon}], sections:[{key,group,title,icon,route,summary,steps[],tips[]}], features}` — rol/ruxsat bo'yicha filtrlangan (`config/guide.php`).

### Online holat va brauzer push (2026-10-14)

- `POST learn/presence` — heartbeat (frontend har 60 s va duel sahifasida). 3 daqiqa ichida heartbeat bo'lgan o'quvchi **online**.
- `GET learn/duels/opponents` — **avval online, keyin offline** (`online`, `last_seen` maydonlari); frontend ikki guruhda ko'rsatadi, 15 s da yangilanadi.
- Push (Firebase Cloud Messaging): `GET push/status`, `POST push/token {token, platform}`, `DELETE push/token {token}`, `POST push/test`.
  Backend `.env`: `FIREBASE_PROJECT_ID`, `FIREBASE_CREDENTIALS` (service account JSON, `storage/app/firebase.json`). Frontend `.env`: `VITE_FIREBASE_*` + `VITE_FIREBASE_VAPID_KEY`; service worker `public/firebase-messaging-sw.js`.
  Push yuboriladigan hodisalar: duel taklifi (`type: duel_invite`, `duel_id`), test biriktirildi, uy ishi berildi/baholandi, baho, daraja o'zgardi, sertifikat, Zoom, oylik hisobot, qarzdorlik (ota-ona akkaunti bo'lsa). Sozlanmagan bo'lsa jim o'tadi.
