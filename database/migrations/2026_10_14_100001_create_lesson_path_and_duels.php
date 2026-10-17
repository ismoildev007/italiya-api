<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars yo'li (XP, dars sessiyalari) va Duel (onlayn "kim birinchi" o'yini, bot raqib).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedInteger('xp')->default(0)->after('speech_game_score');
            $table->unsignedInteger('duel_wins')->default(0)->after('xp');
            $table->unsignedInteger('duel_losses')->default(0)->after('duel_wins');
        });

        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('level', 2);
            $table->string('skill', 20);
            $table->foreignId('course_unit_id')->nullable()->constrained('course_units')->nullOnDelete();
            $table->json('exercises');                  // [{no, type, prompt, pairs|options, answer…}]
            $table->json('answers')->nullable();        // {no: {given, correct, at}}
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('xp_earned')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'level', 'skill']);
        });

        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->string('level', 2);
            $table->foreignId('host_student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('opponent_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('bot_level', 10)->nullable();          // easy | medium | hard
            $table->string('invite_code', 8)->unique();
            $table->string('status', 20)->default('waiting');    // waiting | active | finished | canceled
            $table->json('questions');                            // [{no, body, options[], answer}]
            $table->json('rounds')->nullable();                   // {no: {winner, host:{answer,correct,at}, opponent:{…}, bot:{at_seconds, correct, resolved}}}
            $table->unsignedSmallInteger('current_no')->default(1);
            $table->timestamp('round_started_at')->nullable();
            $table->unsignedSmallInteger('round_seconds')->default(20);
            $table->unsignedSmallInteger('host_score')->default(0);
            $table->unsignedSmallInteger('opponent_score')->default(0);
            $table->string('winner', 10)->nullable();             // host | opponent | draw
            $table->json('xp')->nullable();                       // {host: 20, opponent: 0}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'level']);
            $table->index(['host_student_id', 'status']);
            $table->index(['opponent_student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duels');
        Schema::dropIfExists('lesson_sessions');
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn(['xp', 'duel_wins', 'duel_losses']));
    }
};
