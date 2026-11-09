<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunlik darsliklar: har bir darslikka PDF fayl va "ochilish sanasi" qo'shiladi.
 * O'quvchi faqat sanasi bugun yoki undan oldingi darsliklarni ko'radi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_units', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('media_url');   // kunlik dars PDF fayli
            $table->date('available_on')->nullable()->after('pdf_path'); // dars shu kundan boshlab ochiladi
            $table->index(['level', 'available_on']);
        });
    }

    public function down(): void
    {
        Schema::table('course_units', function (Blueprint $table) {
            $table->dropIndex(['level', 'available_on']);
            $table->dropColumn(['pdf_path', 'available_on']);
        });
    }
};
