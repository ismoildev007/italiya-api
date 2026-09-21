<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Har oy boshida avtomatik generatsiya qilinadi: tarif narxi − chegirma
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();

            $table->string('number', 32)->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->decimal('base_amount', 14, 2)->default(0);      // tarif narxi
            $table->decimal('discount_amount', 14, 2)->default(0);  // qo'llangan chegirma
            $table->decimal('total_amount', 14, 2)->default(0);     // to'lanishi kerak
            $table->decimal('paid_amount', 14, 2)->default(0);      // shu invoice uchun to'langan

            $table->string('status', 20)->default('unpaid'); // unpaid|partial|paid|canceled
            $table->date('due_date')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
