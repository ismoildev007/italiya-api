<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Test bitta o'quvchiga YOKI butun sinfga biriktiriladi (dinamik tanlov)
        Schema::create('test_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('test_template_id')->constrained('test_templates')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();

            $table->string('target', 20)->default('class'); // class | student
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 20)->default('active'); // active | closed
            $table->timestamps();

            $table->index(['class_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('test_assignment_id')->constrained('test_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress'); // in_progress|submitted|graded

            // Har bir o'quvchi uchun savollar tartibi random aralashtiriladi
            $table->json('question_order')->nullable();

            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['test_assignment_id', 'student_id']);
        });

        // Har bir o'quvchining bergan javobi to'liq saqlanadi
        Schema::create('test_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('test_attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $table->foreignId('test_question_id')->constrained('test_questions')->cascadeOnDelete();

            $table->json('selected_option_ids')->nullable(); // variantli savollar uchun
            $table->text('answer_text')->nullable();         // yozma javob

            // Ovozli javob: original audio + Gemini transkripti
            $table->string('audio_path')->nullable();
            $table->text('transcript')->nullable();
            $table->string('transcription_provider', 32)->nullable();
            $table->timestamp('transcribed_at')->nullable();

            $table->json('option_order')->nullable();  // shu o'quvchiga ko'rsatilgan tartib
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 6, 2)->default(0);
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['test_attempt_id', 'test_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_answers');
        Schema::dropIfExists('test_attempts');
        Schema::dropIfExists('test_assignments');
    }
};
