<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('employee_no', 32)->nullable();
            $table->string('specialization')->nullable();   // asosiy yo'nalishi
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();       // male | female
            $table->string('address')->nullable();
            $table->string('passport_number', 32)->nullable();
            $table->date('hired_at')->nullable();

            // Ish haqi rejimi — har bir o'qituvchi uchun alohida tanlanadi
            $table->string('salary_type', 20)->default('fixed'); // fixed | per_lesson
            $table->decimal('fixed_salary', 14, 2)->default(0);   // oylik maosh
            $table->decimal('per_lesson_rate', 14, 2)->default(0); // bir dars narxi

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'employee_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
