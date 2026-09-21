<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Kelajakdagi multi-tenant uchun tayyor ustun (hozircha doim config('school.id'))
            $table->unsignedBigInteger('school_id')->default(1)->index();

            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->string('username')->unique();
            $table->string('phone', 32)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->string('locale', 5)->default('uz');
            $table->string('status', 20)->default('active'); // active | blocked
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
