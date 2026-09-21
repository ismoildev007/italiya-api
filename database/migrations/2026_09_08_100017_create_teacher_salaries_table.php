<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_salaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->string('salary_type', 20);              // hisoblash paytidagi rejim
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->unsignedSmallInteger('lessons_count')->default(0);
            $table->decimal('per_lesson_rate', 14, 2)->default(0);
            $table->decimal('bonus', 14, 2)->default(0);
            $table->decimal('deduction', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->string('status', 20)->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_salaries');
    }
};
