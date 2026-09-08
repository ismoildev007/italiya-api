<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->integer('type')->default(1);      // 0 = System, 1 = User-defined
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            // No timestamps ($timestamps = false on Role model)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
