<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // role_user pivot: user_name → users.username, role_name → roles.name
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('role_name');

            $table->foreign('user_name')
                  ->references('username')->on('users')
                  ->onDelete('cascade');

            $table->foreign('role_name')
                  ->references('name')->on('roles')
                  ->onDelete('cascade');

            $table->primary(['user_name', 'role_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
