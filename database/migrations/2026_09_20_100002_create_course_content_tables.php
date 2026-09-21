<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Darsliklar (course units), materiallar (video/kitob/fayl), o'quvchi progressi
 * va media eshitish hisoblagichi (necha marta tinglandi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('level', 2)->index();                       // A1..C2
            $table->string('track', 20)->default('general')->index();  // general|medical|farming|special
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('skill', 20);                               // listening|reading|writing|speaking|grammar|vocabulary
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();                   // dars matni (markdown)
            $table->text('media_script')->nullable();                  // audio matni (TTS)
            $table->string('media_path')->nullable();                  // audio/video fayl
            $table->string('media_url')->nullable();                   // YouTube / tashqi
            // Necha marta tinglash/ko'rish mumkin (null = cheksiz). Undan keyin material yopiladi.
            $table->unsignedTinyInteger('max_plays')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->foreignId('test_template_id')->nullable()->constrained('test_templates')->nullOnDelete(); // mashq testi
            $table->boolean('is_published')->default(false);
            $table->string('generated_by', 20)->default('manual');   // ai | manual
            $table->string('ai_model')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['level', 'track', 'skill', 'order']);
        });

        Schema::table('test_templates', function (Blueprint $table) {
            $table->foreign('course_unit_id')->references('id')->on('course_units')->nullOnDelete();
        });

        // Guruh kutubxonasi: video darsliklar, kitoblar, fayllar (mockupdagi 3 ustunli panel)
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('course_unit_id')->nullable()->constrained('course_units')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            $table->string('level', 2)->nullable()->index();
            $table->string('track', 20)->nullable()->index();
            $table->string('type', 20)->default('file');   // video | audio | file | book | link
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedTinyInteger('max_plays')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('course_unit_id')->constrained('course_units')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedSmallInteger('plays')->default(0);
            $table->unsignedSmallInteger('views')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();       // mashq testi natijasi
            $table->timestamps();
            $table->unique(['course_unit_id', 'student_id']);
        });

        // Umumiy eshitish hisoblagichi: darslik materiali, test bo'limi yoki savol audiosi
        Schema::create('media_plays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('playable_type', 60);
            $table->unsignedBigInteger('playable_id');
            $table->foreignId('test_attempt_id')->nullable()->constrained('test_attempts')->cascadeOnDelete();
            $table->unsignedSmallInteger('plays')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'playable_type', 'playable_id', 'test_attempt_id'], 'media_plays_unique');
        });

        // Ko'nikmalar bo'yicha progress (mockupdagi skill barlar) — daraja × ko'nikma
        Schema::create('skill_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('level', 2);
            $table->string('skill', 20);
            $table->decimal('percent', 5, 2)->default(0);      // oxirgi natijalar o'rtachasi
            $table->unsignedInteger('points')->default(0);      // yig'ilgan ballar
            $table->unsignedInteger('attempts_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'level', 'skill']);
        });

        Schema::create('student_level_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('from_level', 2)->nullable();
            $table->string('to_level', 2);
            $table->string('reason', 40)->nullable();          // placement | promoted | manual | certificate
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_level_history');
        Schema::dropIfExists('skill_progress');
        Schema::dropIfExists('media_plays');
        Schema::dropIfExists('unit_progress');
        Schema::dropIfExists('course_materials');
        Schema::table('test_templates', fn (Blueprint $t) => $t->dropForeign(['course_unit_id']));
        Schema::dropIfExists('course_units');
    }
};
