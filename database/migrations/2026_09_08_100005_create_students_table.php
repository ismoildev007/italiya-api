<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();

            // O'quvchi tizimga o'z akkaunti bilan kiradi (read-only rol)
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();

            $table->string('student_no', 32);
            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();     // male | female
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();

            // Kelajakdagi Face ID / turniket integratsiyasi uchun tayyor maydonlar
            $table->string('face_id', 128)->nullable()->index();
            $table->string('card_id', 128)->nullable()->index();

            $table->date('enrolled_at')->nullable();
            $table->string('status', 20)->default('active'); // active|graduated|transferred|expelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'student_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
