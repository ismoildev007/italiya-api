<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yakuniy (o'qituvchi tahrirlagan) variant shablon sifatida saqlanadi
        Schema::create('test_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->unsignedTinyInteger('grade')->nullable(); // qaysi sinf darajasiga mo'ljallangan

            $table->string('title');
            $table->string('topic')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->string('difficulty', 20)->default('medium'); // easy|medium|hard|adaptive

            // AI generatsiyasi haqidagi ma'lumot
            $table->string('generated_by', 20)->default('manual'); // ai | manual
            $table->string('ai_model')->nullable();
            $table->text('ai_prompt')->nullable();
            $table->timestamp('ai_generated_at')->nullable();

            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->boolean('is_published')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('test_template_id')->constrained('test_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);

            $table->text('body');
            // single_choice | multiple_choice | short_text | essay
            $table->string('type', 20)->default('single_choice');
            // Javob turi savol darajasida sozlanadi: yozma yoki ovozli
            $table->string('answer_mode', 20)->default('written'); // written | voice

            $table->decimal('score', 6, 2)->default(1);
            $table->text('correct_answer')->nullable();  // matnli savollar uchun etalon javob
            $table->text('explanation')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();

            $table->index(['test_template_id', 'order']);
        });

        Schema::create('test_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_question_id')->constrained('test_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_question_options');
        Schema::dropIfExists('test_questions');
        Schema::dropIfExists('test_templates');
    }
};
