<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sinfga bir nechta fan o'qituvchisi biriktiriladi, har biri o'z faniga mos
        Schema::create('class_subject_teacher', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->unsignedTinyInteger('hours_per_week')->default(2);
            $table->timestamps();

            $table->unique(['class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subject_teacher');
    }
};
