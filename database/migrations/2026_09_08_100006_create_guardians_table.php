<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->string('phone', 32);
            $table->string('workplace')->nullable();
            $table->string('passport_number', 32)->nullable();

            // Telegram bot integratsiyasi uchun (hozircha stub)
            $table->string('telegram_chat_id', 64)->nullable()->index();
            $table->string('link_code', 16)->nullable()->unique(); // farzandga bog'lash kodi
            $table->timestamp('linked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relation', 20)->default('father'); // father|mother|grandparent|other
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
    }
};
