<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();

            $table->decimal('value', 5, 2);
            $table->decimal('max_value', 5, 2)->default(5);
            // daily | homework | test | quarter | exam | annual
            $table->string('type', 20)->default('daily');
            $table->nullableMorphs('source'); // masalan TestAttempt
            $table->date('date');
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'date']);
            $table->index(['class_id', 'subject_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
