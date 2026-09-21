<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->string('name', 16);                 // 5-A
            $table->unsignedTinyInteger('grade');        // 5
            $table->string('letter', 4);                 // A
            $table->unsignedTinyInteger('shift')->default(1); // 1-smena | 2-smena

            // Sinf rahbari — har bir sinfga bitta
            $table->foreignId('class_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();

            $table->string('room', 32)->nullable();
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
