<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telegram bot integratsiyasi uchun event navbati (hozircha stub).
        // Bot keyinchalik shu yozuvlarni o'qib xabar yuboradi.
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();

            // attendance_marked | grade_created | payment_received | debt_reminder
            $table->string('type', 40)->index();
            $table->string('channel', 20)->default('telegram'); // telegram | sms | push

            $table->string('recipient_type', 20)->default('guardian'); // guardian | student | user
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_address', 64)->nullable(); // chat_id / telefon

            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();

            $table->string('status', 20)->default('pending')->index(); // pending|sent|failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
