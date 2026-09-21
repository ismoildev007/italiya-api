<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permission'lar route'lardan avtomatik generatsiya qilinadi:
        // `php artisan permissions:sync`
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('name', 128)->primary();  // masalan: student-index
            $table->string('group', 64)->index();    // masalan: student
            $table->string('title')->nullable();     // UI uchun tavsif
            $table->string('method', 16)->nullable();
            $table->string('uri')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
