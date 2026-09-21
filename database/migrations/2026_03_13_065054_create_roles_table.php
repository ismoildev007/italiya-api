<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            // Rol nomi — inglizcha, texnik kalit (superadmin, admin, teacher, student ...)
            $table->string('name', 64)->primary();
            $table->unsignedBigInteger('school_id')->default(1)->index();

            $table->string('title');                 // UI uchun ko'rinadigan nom
            $table->string('description')->nullable();

            // Tizim roli — hech qachon o'chirilmaydi va nomi o'zgartirilmaydi
            $table->boolean('is_system')->default(false)->index();
            $table->unsignedInteger('level')->default(100); // kichik raqam = yuqori vakolat

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
