<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('cash'); // cash|card|transfer|other
            $table->timestamp('paid_at');
            $table->string('receipt_path')->nullable();    // chek / screenshot fayli
            $table->string('note')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable(); // buxgalter/kassir
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'paid_at']);
        });

        // O'quvchining joriy balansi: musbat = oldindan to'lov, manfiy = qarzdorlik
        Schema::create('student_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('total_charged', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->timestamp('recalculated_at')->nullable();
            $table->timestamps();
        });

        // To'liq moliyaviy tarix — hech narsa yashirin qolmasligi kerak
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // invoice | payment | discount_change | tariff_change | adjustment | refund
            $table->string('type', 30)->index();
            $table->nullableMorphs('reference');            // invoice/payment/discount yozuvi
            $table->decimal('amount', 14, 2);               // invoice: manfiy, payment: musbat
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('student_balances');
        Schema::dropIfExists('payments');
    }
};
