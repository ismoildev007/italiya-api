<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maktab strukturasini til o'quv markaziga (Italiya Akademiyasi) moslashtirish.
 * Mavjud jadvallar o'chirilmaydi — ustunlar qo'shiladi:
 *   school_classes → guruh (daraja + yo'nalish + jadval + zoom)
 *   students       → daraja, yo'nalish, oylik soat, qurilma, telegram
 *   test_templates → IELTS uslubidagi test: daraja, tur, urinish/eshitish limitlari
 *   test_questions → bo'lim, media (listening audio), o'xshashlik chegarasi
 *   test_attempts  → bir nechta urinish (attempt_no), muddat
 *   test_answers   → AI baho va fikr-mulohaza
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Foydalanuvchi sozlamalari: bildirishnoma, ovoz, mavzu, interfeys tili
            $table->json('settings')->nullable()->after('locale');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('level', 2)->nullable()->index()->after('name');       // A1..C2
            $table->string('track', 20)->default('general')->index()->after('level'); // general|medical|farming|special
            $table->json('schedule_days')->nullable()->after('shift');            // [1,3,5] — dushanba, chorshanba, juma
            $table->string('start_time', 5)->nullable()->after('schedule_days');
            $table->string('end_time', 5)->nullable()->after('start_time');
            $table->string('zoom_link')->nullable()->after('room');
            $table->date('starts_on')->nullable()->after('zoom_link');
            $table->text('description')->nullable()->after('starts_on');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('level', 2)->nullable()->index()->after('class_id');   // joriy darajasi
            $table->string('track', 20)->default('general')->after('level');
            $table->unsignedSmallInteger('monthly_hours')->default(0)->after('track');
            $table->string('preferred_language', 2)->default('uz')->after('monthly_hours'); // kartochka orqa tomoni: uz|en|ru
            $table->string('telegram_chat_id', 64)->nullable()->index()->after('preferred_language');
            // Bitta qurilma cheklovi (mockupdagi device binding) — server tomonda
            $table->string('device_id', 128)->nullable()->after('telegram_chat_id');
            $table->timestamp('device_bound_at')->nullable()->after('device_id');
            $table->unsignedInteger('speech_game_score')->default(0)->after('device_bound_at');
            $table->unsignedSmallInteger('streak_days')->default(0)->after('speech_game_score');
            $table->timestamp('last_activity_at')->nullable()->after('streak_days');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('preferred_language', 2)->default('uz')->after('phone');
        });

        Schema::table('test_templates', function (Blueprint $table) {
            // Til o'quv markazida fan ixtiyoriy (asosiy fan — italyan tili)
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->string('level', 2)->nullable()->index()->after('grade');
            $table->string('track', 20)->default('general')->after('level');
            // practice | homework | test | mock | placement
            $table->string('kind', 20)->default('practice')->index()->after('track');
            $table->string('skill', 20)->nullable()->after('kind');              // bitta ko'nikmali test bo'lsa
            $table->string('language', 2)->default('it')->after('skill');
            $table->unsignedTinyInteger('max_attempts')->nullable()->after('duration_minutes'); // null = cheksiz
            $table->unsignedTinyInteger('max_plays')->nullable()->after('max_attempts');       // listening audio (null = cheksiz)
            $table->boolean('auto_grade_ai')->default(true)->after('max_plays');   // writing/speaking AI bilan baholanadi
            $table->boolean('show_answers')->default(true)->after('auto_grade_ai'); // topshirgandan keyin to'g'ri javob ko'rsatiladi
            $table->unsignedTinyInteger('pass_percent')->nullable()->after('show_answers');
            $table->json('time_limits')->nullable()->after('pass_percent');       // {"reading":30,"writing":30}
            $table->unsignedBigInteger('course_unit_id')->nullable()->index()->after('time_limits');
        });

        Schema::create('test_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('test_template_id')->constrained('test_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('skill', 20);                       // listening|reading|writing|speaking|grammar|vocabulary
            $table->string('title')->nullable();
            $table->text('instructions')->nullable();
            $table->longText('passage')->nullable();           // reading matni
            $table->text('media_script')->nullable();          // listening matni (TTS uchun)
            $table->string('media_path')->nullable();          // generatsiya qilingan / yuklangan audio
            $table->string('media_url')->nullable();           // tashqi video/audio
            $table->unsignedTinyInteger('max_plays')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
            $table->index(['test_template_id', 'order']);
        });

        Schema::table('test_questions', function (Blueprint $table) {
            $table->foreignId('test_section_id')->nullable()->after('test_template_id')->constrained('test_sections')->nullOnDelete();
            $table->string('skill', 20)->nullable()->after('type');
            $table->text('media_script')->nullable()->after('explanation');
            $table->string('media_path')->nullable()->after('media_script');
            $table->unsignedTinyInteger('max_plays')->nullable()->after('media_path');
            $table->decimal('similarity_threshold', 3, 2)->nullable()->after('max_plays'); // 0.85 — diktant/gap uchun
            $table->text('model_answer')->nullable()->after('similarity_threshold');       // speaking namunaviy javob
            $table->json('translations')->nullable()->after('model_answer');              // savol tarjimasi {uz,en,ru}
            $table->json('meta')->nullable()->after('translations');
        });

        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropUnique(['test_assignment_id', 'student_id']);
        });

        Schema::table('test_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt_no')->default(1)->after('student_id');
            $table->timestamp('expires_at')->nullable()->after('started_at');
            $table->json('section_progress')->nullable()->after('question_order'); // {"reading": {"started_at":..,"expires_at":..}}
            $table->json('ai_summary')->nullable()->after('percent');
            $table->string('source', 20)->default('web')->after('ai_summary');     // web | telegram
            $table->unique(['test_assignment_id', 'student_id', 'attempt_no'], 'test_attempts_assignment_student_no_unique');
        });

        Schema::table('test_answers', function (Blueprint $table) {
            $table->decimal('similarity', 5, 2)->nullable()->after('is_correct');
            $table->unsignedTinyInteger('plays')->default(0)->after('similarity');
            $table->decimal('ai_score', 6, 2)->nullable()->after('feedback');
            $table->json('ai_feedback')->nullable()->after('ai_score');
            $table->string('ai_provider', 32)->nullable()->after('ai_feedback');
            $table->timestamp('ai_evaluated_at')->nullable()->after('ai_provider');
        });
    }

    public function down(): void
    {
        Schema::table('test_answers', fn (Blueprint $t) => $t->dropColumn(['similarity', 'plays', 'ai_score', 'ai_feedback', 'ai_provider', 'ai_evaluated_at']));
        Schema::table('test_attempts', function (Blueprint $t) {
            $t->dropUnique('test_attempts_assignment_student_no_unique');
            $t->dropColumn(['attempt_no', 'expires_at', 'section_progress', 'ai_summary', 'source']);
        });
        Schema::table('test_questions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('test_section_id');
            $t->dropColumn(['skill', 'media_script', 'media_path', 'max_plays', 'similarity_threshold', 'model_answer', 'translations', 'meta']);
        });
        Schema::dropIfExists('test_sections');
        Schema::table('test_templates', fn (Blueprint $t) => $t->dropColumn(['level', 'track', 'kind', 'skill', 'language', 'max_attempts', 'max_plays', 'auto_grade_ai', 'show_answers', 'pass_percent', 'time_limits', 'course_unit_id']));
        Schema::table('guardians', fn (Blueprint $t) => $t->dropColumn('preferred_language'));
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn(['level', 'track', 'monthly_hours', 'preferred_language', 'telegram_chat_id', 'device_id', 'device_bound_at', 'speech_game_score', 'streak_days', 'last_activity_at']));
        Schema::table('school_classes', fn (Blueprint $t) => $t->dropColumn(['level', 'track', 'schedule_days', 'start_time', 'end_time', 'zoom_link', 'starts_on', 'description']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('settings'));
    }
};
