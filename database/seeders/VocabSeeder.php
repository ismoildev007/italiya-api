<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use Database\Seeders\Data\ItalianContent;
use Illuminate\Database\Seeder;

/**
 * Ovozli lug'at (kartochkalar: it ↔ uz/en/ru) va so'zlashuv o'yinlari + liderbord uchun natijalar.
 */
class VocabSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(4242);
        $teacherByLevel = SchoolClass::get()->groupBy('level')->map(fn ($g) => $g->first()->class_teacher_id);
        $decks = 0;
        $cards = 0;

        $create = function (string $level, string $track, string $title, string $category, array $rows, string $source) use (&$decks, &$cards, $teacherByLevel) {
            $deck = VocabDeck::create([
                'school_id' => config('school.id'),
                'teacher_id' => $teacherByLevel->get($level),
                'title' => $title,
                'level' => $level,
                'track' => $track,
                'category' => $category,
                'language' => 'it',
                'is_published' => true,
                'generated_by' => $source,
            ]);
            $decks++;

            foreach ($rows as $i => [$word, $uz, $en, $ru, $example]) {
                VocabCard::create([
                    'school_id' => config('school.id'),
                    'vocab_deck_id' => $deck->id,
                    'order' => $i + 1,
                    'word' => $word,
                    'translations' => ['uz' => $uz, 'en' => $en, 'ru' => $ru],
                    'category' => $category,
                    'example' => $example,
                    'ai_generated' => $source === 'ai',
                ]);
                $cards++;
            }

            $deck->recount();

            return $deck;
        };

        $deckByLevel = [];
        foreach (ItalianContent::levels() as $level => $c) {
            $deckByLevel[$level] = $create($level, 'general', "{$level}: {$c['vocabulary']['category']}", $c['vocabulary']['category'], $c['vocabulary']['cards'], 'ai');
        }

        foreach (ItalianContent::trackVocabulary() as $track => $v) {
            $create($v['level'], $track, $v['title'], $v['category'], $v['cards'], 'import');
        }

        // O'yinlar: har bir daraja uchun 2 ta (kartochka-tanlash va talaffuz), savollar soni belgilangan
        $games = 0;
        foreach ($deckByLevel as $level => $deck) {
            foreach ([
                ['choice', "{$level} So'z moslashtirish", ['it-to-tr', 'tr-to-it'], 10, 15],
                ['typing', "{$level} So'zni yozish", ['typing'], 8, 20],
            ] as [$type, $title, $rounds, $count, $seconds]) {
                Game::create([
                    'school_id' => config('school.id'),
                    'teacher_id' => $deck->teacher_id,
                    'vocab_deck_id' => $deck->id,
                    'title' => $title,
                    'type' => $type,
                    'level' => $level,
                    'track' => 'general',
                    'card_count' => $count,
                    'seconds_per_card' => $seconds,
                    'round_types' => $rounds,
                    'prompt_language' => 'it',
                    'points_per_correct' => 10,
                    'is_active' => true,
                ]);
                $games++;
            }
        }

        // Natijalar (liderbord uchun)
        $sessions = 0;
        foreach (Game::with('deck.cards')->get() as $game) {
            foreach (Student::where('level', $game->level)->limit(6)->get() as $i => $student) {
                $total = $game->card_count;
                $correct = max(2, $total - ($i * 2 + $game->id) % 6);
                $score = $correct * $game->points_per_correct;

                GameSession::create([
                    'school_id' => config('school.id'),
                    'game_id' => $game->id,
                    'student_id' => $student->id,
                    'started_at' => now()->subDays(1 + $i)->subMinutes(5),
                    'finished_at' => now()->subDays(1 + $i),
                    'total_rounds' => $total,
                    'correct' => $correct,
                    'wrong' => $total - $correct,
                    'score' => $score,
                    'duration_seconds' => 180 + $i * 20,
                    'rounds' => [],
                    'details' => [],
                ]);
                $student->increment('speech_game_score', $score);
                $sessions++;
            }
        }

        $this->command?->info("✅ {$decks} ta lug'at to'plami ({$cards} kartochka, uz/en/ru), {$games} ta o'yin, {$sessions} ta o'yin natijasi.");
    }
}
