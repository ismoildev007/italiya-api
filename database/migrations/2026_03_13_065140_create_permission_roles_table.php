<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // permission_role pivot: role_name → roles.name, permission_name → permissions.name
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('permission_name');

            $table->foreign('role_name')
                  ->references('name')->on('roles')
                  ->onDelete('cascade');

            $table->foreign('permission_name')
                  ->references('name')->on('permissions')
                  ->onDelete('cascade');

            $table->primary(['role_name', 'permission_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
