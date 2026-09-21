<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();

            $table->date('date');
            $table->string('status', 20)->default('present'); // present|absent|late|excused

            // Yozuv qayerdan kelgani. Hozircha faqat `manual`.
            // Kelajakda Face ID / turniket `device` qiymati bilan yozadi —
            // mavjud strukturaga tegilmaydi.
            $table->string('source', 20)->default('manual');  // manual | device
            $table->string('device_id', 64)->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->unsignedBigInteger('marked_by')->nullable(); // sinf rahbari / o'qituvchi
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date']);
            $table->index(['class_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
