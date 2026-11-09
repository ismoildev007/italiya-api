<?php

namespace App\Services;

use App\Models\CourseUnit;
use App\Models\Game;
use App\Models\Teacher;
use App\Models\TestQuestion;
use App\Models\TestTemplate;
use App\Models\VocabDeck;
use App\Support\SpreadsheetReader;
use App\Support\VocabSheetParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Bitta Excel/CSV fayl → lug'at to'plami + so'z o'yini + mashq testi.
 * Testlar va o'yinlar hammasi shu bitta format orqali kiritiladi:
 * [sarlavha] → № | Italiya so'zi | O'zbekcha tarjimasi | Kategoriya.
 */
class VocabImportService
{
    public function __construct(
        private readonly VocabService $vocab,
        private readonly GameService $games,
        private readonly TestService $tests,
    ) {}

    /**
     * @return array{deck: VocabDeck, game: ?Game, test: ?TestTemplate, imported: int}
     */
    public function importBundle(UploadedFile $file, array $data): array
    {
        $rows = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension());

        abort_if(count($rows) < 1, 422, 'Fayl bo\'sh.');

        $parsed = VocabSheetParser::parse($rows);
        $items = $parsed['items'];

        abort_if(count($items) < 2, 422, 'Faylda kamida 2 ta so\'z | tarjima qatori bo\'lishi kerak.');

        $title = $data['title'] ?? $parsed['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $published = (bool) ($data['is_published'] ?? true);
        $teacherId = $data['teacher_id'] ?? Teacher::where('user_id', auth()->id())->value('id');

        return DB::transaction(function () use ($items, $parsed, $data, $title, $published, $teacherId) {
            $deck = $this->vocab->save([
                'teacher_id' => $teacherId,
                'title' => $title,
                'level' => $data['level'],
                'track' => $data['track'] ?? 'general',
                'category' => $data['category'] ?? $this->dominantCategory($items),
                'description' => $parsed['description'],
                'is_published' => $published,
                'generated_by' => 'import',
            ]);

            $imported = $this->vocab->appendCards($deck, $items);
            $deck->recount();

            $game = null;
            $test = null;

            if (($data['create_game'] ?? true) && $imported >= 3) {
                $game = $this->games->save([
                    'teacher_id' => $teacherId,
                    'vocab_deck_id' => $deck->id,
                    'title' => $title,
                    'type' => $data['game_type'] ?? 'choice',
                    'level' => $data['level'],
                    'track' => $data['track'] ?? 'general',
                    'card_count' => min($data['card_count'] ?? config('academy.games.default_card_count'), $imported),
                    'is_active' => $published,
                ]);
            }

            if ($data['create_test'] ?? true) {
                $count = min($data['question_count'] ?? min($imported, 30), $imported);

                $test = $this->tests->saveTemplate([
                    'teacher_id' => $teacherId,
                    'title' => "{$title} — test",
                    'topic' => $deck->category,
                    'description' => $parsed['description'],
                    'level' => $data['level'],
                    'track' => $data['track'] ?? 'general',
                    'kind' => TestTemplate::KIND_PRACTICE,
                    'skill' => 'vocabulary',
                    'duration_minutes' => max(5, (int) ceil($count * 0.5)),
                    'show_answers' => true,
                    'is_published' => $published,
                    'course_unit_id' => $data['course_unit_id'] ?? null,
                    'generated_by' => TestTemplate::SOURCE_MANUAL,
                    'questions' => $this->buildQuestions($items, $count),
                ]);

                // Kunlik darsga mashq testi sifatida biriktiramiz (bo'sh bo'lsa)
                if (! empty($data['course_unit_id'])) {
                    CourseUnit::whereKey($data['course_unit_id'])->whereNull('test_template_id')->update(['test_template_id' => $test->id]);
                }
            }

            return [
                'deck' => $deck->fresh(['cards', 'teacher.user']),
                'game' => $game,
                'test' => $test,
                'imported' => $imported,
            ];
        });
    }

    /**
     * So'zlardan variantli savollar: navbat bilan it→uz ("tarjimasi qaysi?") va uz→it
     * ("italyanchasi qaysi?"), 4 ta variant — chalg'ituvchilar avval shu kategoriyadan olinadi.
     *
     * @param  array<int, array{word: string, translations: array<string, string>, example: ?string, category: ?string}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function buildQuestions(array $items, int $count): array
    {
        $pool = collect($items)->filter(fn ($i) => filled($i['translations']['uz'] ?? null))->values();
        $picked = $pool->shuffle()->take($count)->values();
        $questions = [];

        foreach ($picked as $index => $item) {
            $reverse = $index % 2 === 1;
            $correct = $reverse ? $item['word'] : $item['translations']['uz'];

            $distractors = $pool
                ->reject(fn ($other) => $other['word'] === $item['word'])
                ->map(fn ($other) => $reverse ? $other['word'] : $other['translations']['uz'])
                ->reject(fn ($value) => mb_strtolower($value) === mb_strtolower($correct))
                ->unique(fn ($value) => mb_strtolower($value));

            // Avval bir xil kategoriyadagilar, yetmasa qolganlardan
            $sameCategory = $pool->filter(fn ($other) => $other['category'] !== null && $other['category'] === $item['category'] && $other['word'] !== $item['word'])
                ->map(fn ($other) => $reverse ? $other['word'] : $other['translations']['uz']);

            $options = $sameCategory->shuffle()->values()->intersect($distractors)->take(3);
            $options = $options->concat($distractors->diff($options)->shuffle()->take(3 - $options->count()))->values();

            $all = $options->map(fn ($body) => ['body' => $body, 'is_correct' => false])
                ->push(['body' => $correct, 'is_correct' => true])
                ->shuffle()
                ->values()
                ->map(fn ($option, $i) => $option + ['order' => $i + 1])
                ->all();

            $questions[] = [
                'order' => $index + 1,
                'type' => TestQuestion::TYPE_SINGLE,
                'skill' => 'vocabulary',
                'score' => 1,
                'body' => $reverse
                    ? "«{$item['translations']['uz']}» so'zining italyanchasi qaysi?"
                    : "«{$item['word']}» so'zining tarjimasi qaysi?",
                'correct_answer' => $correct,
                'explanation' => $item['example'] ?: ($item['category'] ? "Kategoriya: {$item['category']}" : null),
                'options' => $all,
            ];
        }

        return $questions;
    }

    /** Eng ko'p uchragan kategoriya — to'plam kategoriyasi sifatida */
    private function dominantCategory(array $items): ?string
    {
        return collect($items)->pluck('category')->filter()->countBy()->sortDesc()->keys()->first();
    }
}
