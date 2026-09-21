<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uy ishi va topshiriqlar, Zoom mitinglar, sertifikatlar, oylik hisobotlar
 * va Telegram bot sessiyalari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->string('level', 2)->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('test_template_id')->nullable()->constrained('test_templates')->nullOnDelete();
            $table->foreignId('course_unit_id')->nullable()->constrained('course_units')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->string('status', 20)->default('active'); // active | closed
            $table->timestamps();
            $table->softDeletes();
            $table->index(['class_id', 'status']);
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->string('file_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->foreignId('test_attempt_id')->nullable()->constrained('test_attempts')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending | checked | returned
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['homework_id', 'student_id']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('platform', 20)->default('zoom');  // zoom | google_meet | other
            $table->string('link');
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->json('levels')->nullable();                 // ["A1","A2"] — darajalar bo'yicha
            $table->json('tracks')->nullable();                 // ["medical"]
            $table->string('status', 20)->default('scheduled'); // scheduled | live | finished | canceled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'status']);
        });

        Schema::create('meeting_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->unique(['meeting_id', 'class_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('serial', 40)->unique();            // ITA-2026-09-0001
            $table->string('level', 2);
            $table->string('track', 20)->default('general');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('title')->nullable();
            // Mockup formulasi: davomat 40% + uy ishi 30% + test 30%
            $table->decimal('attendance_score', 5, 2)->default(0);
            $table->decimal('homework_score', 5, 2)->default(0);
            $table->decimal('test_score', 5, 2)->default(0);
            $table->decimal('overall_score', 5, 2)->default(0);
            $table->json('skills')->nullable();                 // {"listening": 72, ...}
            $table->string('grade_label', 40)->nullable();     // Eccellente / Ottimo / Buono / Sufficiente
            $table->string('verify_code', 16)->unique();
            $table->timestamp('issued_at');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'period_year', 'period_month']);
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->json('data');                               // statistika (testlar, ko'nikmalar, davomat, o'yinlar)
            $table->text('summary')->nullable();                // AI xulosasi (o'zbekcha)
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'year', 'month']);
        });

        Schema::create('telegram_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('bot', 20)->default('student');      // student | parent
            $table->string('chat_id', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('state', 40)->default('idle');       // idle | await_phone | await_password | in_test
            $table->json('context')->nullable();                // joriy attempt, savol indeksi…
            $table->string('phone', 32)->nullable();
            $table->string('language', 2)->default('uz');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['bot', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_sessions');
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('meeting_class');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homeworks');
    }
};
