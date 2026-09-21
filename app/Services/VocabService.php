<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Services\Ai\AiManager;
use App\Services\Ai\LocalAi;
use App\Services\Ai\SpeechService;
use App\Support\Level;
use App\Support\SpreadsheetReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ovozli lug'at: to'plamlar (daraja/yo'nalish), kartochkalar (old — italyancha,
 * orqa — uz/en/ru), AI generatsiya, Excel/CSV import va TTS talaffuz.
 */
class VocabService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly SpeechService $speech,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return VocabDeck::query()
            ->with('teacher.user')
            ->search($filters['search'] ?? null, ['title', 'category'])
            ->filter($filters, [
                'level' => 'level',
                'track' => 'track',
                'category' => ['category', 'like'],
                'is_published' => ['is_published', 'bool'],
            ])
            ->orderBy('level')->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(array $data, ?VocabDeck $deck = null): VocabDeck
    {
        return DB::transaction(function () use ($data, $deck) {
            $payload = [
                'school_id' => config('school.id'),
                'teacher_id' => $data['teacher_id'] ?? $deck?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id'),
                'title' => $data['title'] ?? $deck?->title,
                'level' => $data['level'] ?? $deck?->level,
                'track' => $data['track'] ?? $deck?->track ?? 'general',
                'category' => $data['category'] ?? $deck?->category,
                'language' => $data['language'] ?? $deck?->language ?? config('academy.target_language'),
                'description' => $data['description'] ?? $deck?->description,
                'is_published' => $data['is_published'] ?? $deck?->is_published ?? true,
                'generated_by' => $data['generated_by'] ?? $deck?->generated_by ?? 'manual',
                'created_by' => $deck?->created_by ?? auth()->id(),
            ];

            $deck = $deck ? tap($deck)->update($payload) : VocabDeck::create($payload);

            if (array_key_exists('cards', $data)) {
                $this->syncCards($deck, $data['cards']);
            }

            $deck->recount();

            return $deck->fresh(['cards', 'teacher.user']);
        });
    }

    public function delete(VocabDeck $deck): void
    {
        $deck->delete();
    }

    /** Kartochkalarni to'liq almashtiradi (id berilganlar yangilanadi). */
    private function syncCards(VocabDeck $deck, array $cards): void
    {
        $keep = [];

        foreach ($cards as $index => $data) {
            $card = ! empty($data['id'])
                ? VocabCard::where('vocab_deck_id', $deck->id)->findOrFail($data['id'])
                : new VocabCard(['vocab_deck_id' => $deck->id]);

            $card->fill([
                'school_id' => config('school.id'),
                'vocab_deck_id' => $deck->id,
                'order' => $data['order'] ?? $index + 1,
                'word' => $data['word'],
                'translations' => $this->normalizeTranslations($data['translations'] ?? $data['translation'] ?? []),
                'category' => $data['category'] ?? $deck->category,
                'part_of_speech' => $data['part_of_speech'] ?? null,
                'example' => $data['example'] ?? null,
                'example_translations' => isset($data['example_translations']) ? $this->normalizeTranslations($data['example_translations']) : null,
                'pronunciation' => $data['pronunciation'] ?? null,
                'ai_generated' => $data['ai_generated'] ?? false,
            ])->save();

            $keep[] = $card->id;
        }

        VocabCard::where('vocab_deck_id', $deck->id)->whereNotIn('id', $keep ?: [0])->delete();
    }

    /** "salom" → {"uz": "salom"}; {"uz": …} → o'zi */
    private function normalizeTranslations(string|array $value): array
    {
        if (is_string($value)) {
            return ['uz' => trim($value)];
        }

        return array_filter(array_map(fn ($v) => trim((string) $v), $value), fn ($v) => $v !== '');
    }

    /**
     * Excel/CSV import: so'z | tarjima (uz) | [en] | [ru] | [misol] | [kategoriya].
     * Sarlavha qatori avtomatik aniqlanadi. Qo'shilgan kartalar sonini qaytaradi.
     */
    public function import(VocabDeck $deck, UploadedFile $file): int
    {
        $rows = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension());

        abort_if(count($rows) < 1, 422, 'Fayl bo\'sh.');

        $header = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $rows[0]);
        $hasHeader = collect($header)->contains(fn ($h) => preg_match('/so.?z|word|parola|italy/u', $h))
            && collect($header)->contains(fn ($h) => preg_match('/tarjima|translation|javob|uzbek|o.?zbek/u', $h));

        $map = ['word' => 0, 'uz' => 1, 'en' => null, 'ru' => null, 'example' => null, 'category' => null];

        if ($hasHeader) {
            foreach ($header as $i => $h) {
                match (true) {
                    (bool) preg_match('/^(en|eng|english|ingliz)/u', $h) => $map['en'] = $i,
                    (bool) preg_match('/^(ru|rus|russian|русск)/u', $h) => $map['ru'] = $i,
                    (bool) preg_match('/misol|example|esempio/u', $h) => $map['example'] = $i,
                    (bool) preg_match('/kategor|category|mavzu/u', $h) => $map['category'] = $i,
                    default => null,
                };
            }
            $rows = array_slice($rows, 1);
        } else {
            $map['en'] = 2;
            $map['ru'] = 3;
            $map['example'] = 4;
            $map['category'] = 5;
        }

        $order = (int) $deck->cards()->max('order');
        $added = 0;

        foreach ($rows as $row) {
            $word = trim((string) ($row[$map['word']] ?? ''));
            $uz = trim((string) ($row[$map['uz']] ?? ''));

            if ($word === '' || $uz === '') {
                continue;
            }

            $translations = array_filter([
                'uz' => $uz,
                'en' => $map['en'] !== null ? trim((string) ($row[$map['en']] ?? '')) : '',
                'ru' => $map['ru'] !== null ? trim((string) ($row[$map['ru']] ?? '')) : '',
            ]);

            VocabCard::create([
                'school_id' => config('school.id'),
                'vocab_deck_id' => $deck->id,
                'order' => ++$order,
                'word' => $word,
                'translations' => $translations,
                'example' => $map['example'] !== null ? (trim((string) ($row[$map['example']] ?? '')) ?: null) : null,
                'category' => $map['category'] !== null ? (trim((string) ($row[$map['category']] ?? '')) ?: $deck->category) : $deck->category,
            ]);

            $added++;
        }

        $deck->update(['generated_by' => 'import']);
        $deck->recount();

        return $added;
    }

    /**
     * AI orqali daraja/mavzu bo'yicha so'zlar ro'yxati (bazaga yozilmaydi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateCards(array $params): array
    {
        $level = $params['level'];
        $count = (int) ($params['count'] ?? 15);
        $topic = $params['topic'] ?? 'kundalik hayot';
        $track = $params['track'] ?? 'general';
        $languages = $params['languages'] ?? ['uz', 'en'];
        $langList = implode(', ', $languages);

        $prompt = <<<PROMPT
        Italyan tili {$level} ({$this->levelTitle($level)}) darajasi, «{$topic}» mavzusi, yo'nalish: {$this->trackTitle($track)}.
        {$count} ta eng foydali so'z/ibora tanla. Har biri uchun tarjimalar ({$langList}), qisqa italyancha misol gap va uning tarjimalari.

        JSON:
        {"cards": [{"word": "…", "part_of_speech": "sost.|verbo|agg.|avv.|espr.", "translations": {"uz": "…", "en": "…"}, "example": "…", "example_translations": {"uz": "…"}, "pronunciation": "…"}]}
        PROMPT;

        $result = [];

        if ($this->ai->remoteAvailable('generator')) {
            try {
                $result = $this->ai->for('generator')->json($prompt, 'Sen italyan tili leksika o\'qituvchisisan. Faqat JSON qaytarasan.', ['temperature' => 0.5]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (empty($result['cards'])) {
            return app(LocalAi::class)->generateCards($level, $count, $topic, $track, $languages);
        }

        return collect($result['cards'] ?? [])
            ->filter(fn ($c) => ! empty($c['word']) && ! empty($c['translations']))
            ->map(fn ($c, $i) => [
                'order' => $i + 1,
                'word' => (string) $c['word'],
                'part_of_speech' => $c['part_of_speech'] ?? null,
                'translations' => (array) $c['translations'],
                'example' => $c['example'] ?? null,
                'example_translations' => isset($c['example_translations']) ? (array) $c['example_translations'] : null,
                'pronunciation' => $c['pronunciation'] ?? null,
                'ai_generated' => true,
            ])->values()->all();
    }

    /** Barcha kartochkalar uchun TTS audio (mavjud bo'lmaganlarga). */
    public function generateAudio(VocabDeck $deck, bool $force = false): int
    {
        $count = 0;

        foreach ($deck->cards()->get() as $card) {
            if ($card->audio_path && ! $force) {
                continue;
            }

            $path = $this->speech->synthesize($card->word.'. '.($card->example ?? ''), "vocab/{$deck->id}", null, 'Pronounce clearly in Italian, slowly.');

            if ($path) {
                $card->update(['audio_path' => $path]);
                $count++;
            }
        }

        return $count;
    }

    /* ---------------- O'quvchi tomoni ---------------- */

    /** O'quvchi darajasiga (va undan pastiga) mos to'plamlar. */
    public function forStudent(Student $student, array $filters = []): Collection
    {
        $level = $filters['level'] ?? $student->effectiveLevel();
        $ordinal = Level::ordinal($level);
        $allowed = collect(Level::all())->filter(fn ($l) => Level::ordinal($l) <= $ordinal)->values()->all();

        return VocabDeck::where('is_published', true)
            ->whereIn('level', $allowed ?: [$level])
            ->whereIn('track', array_unique([$student->effectiveTrack(), 'general']))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', 'like', "%{$c}%"))
            ->orderByDesc('level')->orderBy('id')
            ->get();
    }

    /**
     * Kartochkalar — old tomoni italyancha, orqa tomoni tanlangan tilda.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cardsFor(VocabDeck $deck, string $lang, bool $shuffle = false): array
    {
        $cards = $deck->cards()->get();

        if ($shuffle) {
            $cards = $cards->shuffle();
        }

        return $cards->map(fn (VocabCard $card) => [
            'id' => $card->id,
            'front' => $card->word,
            'front_language' => $deck->language,
            'back' => $card->translation($lang),
            'back_language' => $lang,
            'translations' => $card->translations,
            'category' => $card->category,
            'part_of_speech' => $card->part_of_speech,
            'example' => $card->example,
            'example_translation' => $card->exampleTranslation($lang),
            'pronunciation' => $card->pronunciation,
            'audio_url' => $card->audio_url,
        ])->values()->all();
    }

    private function levelTitle(string $level): string
    {
        return Level::title($level);
    }

    private function trackTitle(string $track): string
    {
        return (string) config("academy.tracks.{$track}", $track);
    }
}
