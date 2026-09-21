<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->string('role_name', 64);
            $table->string('permission_name', 128);

            $table->primary(['role_name', 'permission_name']);

            $table->foreign('role_name')->references('name')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_name')->references('name')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
