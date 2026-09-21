<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ovozli lug'at (kartochkalar — old tomoni italyancha, orqasi o'zbekcha/inglizcha/ruscha)
 * va o'yinlar (har bir daraja uchun, savollar soni belgilanadi) + natijalar (liderbord).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocab_decks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('title');
            $table->string('level', 2)->index();
            $table->string('track', 20)->default('general')->index();
            $table->string('category')->nullable();             // Salomlashish, Oziq-ovqat, Tibbiy atamalar…
            $table->string('language', 2)->default('it');       // old tomon tili
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cards_count')->default(0);
            $table->boolean('is_published')->default(true);
            $table->string('generated_by', 20)->default('manual'); // ai | manual | import
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vocab_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('vocab_deck_id')->constrained('vocab_decks')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('word');                              // italyancha (old tomon)
            $table->json('translations');                        // {"uz": "...", "en": "...", "ru": "..."}
            $table->string('category')->nullable();
            $table->string('part_of_speech', 20)->nullable();
            $table->text('example')->nullable();                 // italyancha misol
            $table->json('example_translations')->nullable();
            $table->string('pronunciation')->nullable();
            $table->string('audio_path')->nullable();            // TTS talaffuz
            $table->string('image_path')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
            $table->index(['vocab_deck_id', 'order']);
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('vocab_deck_id')->nullable()->constrained('vocab_decks')->nullOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('flashcard');   // flashcard|match|choice|speech|listening|typing
            $table->string('level', 2)->index();
            $table->string('track', 20)->default('general');
            $table->unsignedSmallInteger('card_count')->default(10);       // o'yin (savol) soni
            $table->unsignedSmallInteger('seconds_per_card')->default(15);
            $table->json('round_types')->nullable();             // ["it-to-uz","uz-to-it","voice"]
            $table->string('prompt_language', 2)->default('it');
            $table->string('answer_language', 2)->nullable();   // null → o'quvchining preferred_language
            $table->unsignedTinyInteger('points_per_correct')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('total_rounds')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('wrong')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('rounds')->nullable();                  // o'quvchiga berilgan savollar
            $table->json('details')->nullable();                 // javoblar tarixi
            $table->string('source', 20)->default('web');
            $table->timestamps();
            $table->index(['game_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('games');
        Schema::dropIfExists('vocab_cards');
        Schema::dropIfExists('vocab_decks');
    }
};
