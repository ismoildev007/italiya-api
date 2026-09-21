<?php

namespace App\Services\Ai;

use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Support\Level;
use App\Support\TextSimilarity;
use Database\Seeders\Data\ItalianContent;

/**
 * "Oddiy AI" — tashqi kalitsiz ishlaydigan lokal zaxira.
 * Tashqi provayder (Gemini/OpenAI/Claude) sozlanmagan yoki xato bersa,
 * barcha AI funksiyalar shu sinf orqali baribir ishlaydi:
 *   - test / darslik / lug'at generatsiyasi — tayyor bankdan (ItalianContent) + bazadagi kartochkalardan tuziladi
 *   - writing / speaking baholash — evristik (so'z soni, namunaga o'xshashlik, italyancha so'zlar ulushi)
 *   - oylik xulosa — shablon matn
 */
class LocalAi
{
    public const NAME = 'local';

    public const MODEL = 'rule-based';

    private const ITALIAN_HINTS = ['il', 'la', 'di', 'che', 'e', 'è', 'un', 'una', 'non', 'per', 'con', 'sono', 'ho', 'mi', 'si', 'in', 'a', 'da', 'del', 'della', 'gli', 'le', 'ma', 'anche', 'come', 'molto', 'più', 'perché', 'quando', 'sempre'];

    /* ------------------------------------------------------------------ */
    /* Generatsiya */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{title:string, description:?string, sections:array}
     */
    public function generateTest(string $level, array $skills, int $count, string $kind, ?string $topic, string $track = 'general', string $lang = 'uz'): array
    {
        $c = $this->content($level);
        $sections = [];

        foreach ($skills as $skill) {
            $questions = $this->bankQuestions($c, $skill);

            // Yetmasa — lug'at/grammatika savollari bilan to'ldiramiz
            while (count($questions) < $count) {
                $extra = $this->extraQuestion($level, $track, $skill, count($questions));

                if ($extra === null) {
                    break;
                }

                $questions[] = $extra;
            }

            $questions = array_slice($questions, 0, max(1, $count));

            foreach ($questions as $i => &$q) {
                $q['order'] = $i + 1;
                $q['skill'] = $skill;
                $q['ai_generated'] = true;
                $q['translations'] ??= null;
            }

            $sections[] = [
                'order' => count($sections) + 1,
                'skill' => $skill,
                'title' => config("academy.skills.{$skill}"),
                'instructions' => $this->instructions($skill),
                'passage' => $skill === 'reading' ? $c['reading']['passage'] : null,
                'media_script' => $skill === 'listening' ? $c['listening']['script'] : null,
                'time_limit_minutes' => config("academy.defaults.section_minutes.{$skill}"),
                'ai_generated' => true,
                'questions' => $questions,
            ];
        }

        $topicLine = $topic ?: $c['topic'];

        return [
            'title' => "{$level} — {$topicLine} (".implode(', ', array_map(fn ($s) => config("academy.skills.{$s}"), $skills)).')',
            'description' => "{$level} darajasi, «{$topicLine}» mavzusi. Savollar lokal bankdan tuzildi — o'qituvchi tahrirlashi mumkin.",
            'sections' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    public function generateUnit(string $level, string $skill, string $track, ?string $topic, string $lang = 'uz'): array
    {
        $c = $this->content($level);
        $topicLine = $topic ?: $c['topic'];
        $vocab = array_map(fn ($card) => ['word' => $card[0], 'translation' => $card[1], 'example' => $card[4]], $c['vocabulary']['cards']);
        $vocabTable = implode("\n", array_map(fn ($v) => "| {$v['word']} | {$v['translation']} | {$v['example']} |", $vocab));

        $body = match ($skill) {
            'listening' => "## Tinglash\n\nAudioni diqqat bilan tinglang va asosiy ma'lumotlarni yozib oling.\n\n**Matn:**\n\n> {$c['listening']['script']}",
            'reading' => "## O'qish: {$c['reading']['title']}\n\n{$c['reading']['passage']}\n\n**Mashq:** matndagi yangi so'zlarni belgilang va tarjima qiling.",
            'writing' => "## Yozish\n\n**Topshiriq:** {$c['writing']['essay'][0]}\n\n**Namunaviy javob:**\n\n> {$c['writing']['essay'][1]}\n\n**Tuzilma:** kirish → asosiy qism → xulosa. Bog'lovchilar: *inoltre, tuttavia, quindi, infine*.",
            'speaking' => "## Gapirish\n\n".implode("\n", array_map(fn ($s) => "- **{$s[0]}** — {$s[1]}", $c['speaking'])),
            'grammar' => "## Grammatika\n\nBo'shliqlarni to'ldiring:\n\n".implode("\n", array_map(fn ($g) => "- {$g[0]} (*javob: {$g[1]}*)", $c['writing']['gaps'])),
            default => "## Lug'at\n\n| So'z | Tarjima | Misol |\n|---|---|---|\n{$vocabTable}",
        };

        $practice = [];
        foreach (array_slice($c['vocabulary']['cards'], 0, 5) as $i => $card) {
            $practice[] = $this->vocabChoice($card, $c['vocabulary']['cards']);
        }

        return [
            'title' => config("academy.skills.{$skill}").": {$topicLine}",
            'description' => "{$level} darajasi, «{$topicLine}» mavzusi — ".config("academy.skills.{$skill}"),
            'content' => $body."\n\n---\n### Yangi so'zlar\n\n| So'z | Tarjima | Misol |\n|---|---|---|\n{$vocabTable}",
            'media_script' => in_array($skill, ['listening', 'speaking'], true) ? $c['listening']['script'] : null,
            'vocabulary' => $vocab,
            'practice_questions' => $practice,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function generateCards(string $level, int $count, ?string $topic, string $track = 'general', array $languages = ['uz', 'en']): array
    {
        $pool = [];

        foreach ($this->content($level)['vocabulary']['cards'] as $card) {
            $pool[] = $card;
        }

        foreach (ItalianContent::trackVocabulary() as $t => $v) {
            if ($t === $track) {
                $pool = array_merge($v['cards'], $pool);
            }
        }

        $cards = [];

        foreach (array_slice($pool, 0, $count) as $i => [$word, $uz, $en, $ru, $example]) {
            $translations = array_intersect_key(['uz' => $uz, 'en' => $en, 'ru' => $ru], array_flip($languages ?: ['uz']));
            $cards[] = [
                'order' => $i + 1,
                'word' => $word,
                'part_of_speech' => null,
                'translations' => $translations ?: ['uz' => $uz],
                'example' => $example,
                'example_translations' => null,
                'pronunciation' => null,
                'ai_generated' => true,
            ];
        }

        return $cards;
    }

    /* ------------------------------------------------------------------ */
    /* Baholash (evristik) */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    public function evaluateWriting(string $text, ?string $modelAnswer, ?int $minWords, ?string $level): array
    {
        $text = trim($text);
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
        $min = $minWords ?: match ($level) {
            'A1' => 30, 'A2' => 50, 'B1' => 80, 'B2' => 120, 'C1' => 180, default => 60
        };

        $lengthScore = min(100, (int) round($words / max(1, $min) * 100));
        $italianScore = $this->italianRatio($text);
        $similarity = $modelAnswer ? (int) round(TextSimilarity::ratio($text, $modelAnswer) * 100) : null;
        $structure = (preg_match('/^\p{Lu}/u', $text) ? 50 : 0) + (preg_match('/[.!?]\s*$/u', $text) ? 50 : 0);

        $score = $words === 0 ? 0 : (int) round($lengthScore * 0.4 + $italianScore * 0.3 + ($similarity ?? $italianScore) * 0.1 + $structure * 0.2);

        $feedback = [];
        if ($words === 0) {
            $feedback[] = 'Javob yozilmagan.';
        }
        if ($words > 0 && $words < $min) {
            $feedback[] = "Matn qisqa: {$words} ta so'z, kamida {$min} ta kerak.";
        }
        if ($italianScore < 40 && $words > 0) {
            $feedback[] = 'Matnda italyancha so\'zlar kam — javobni italyan tilida yozing.';
        }
        if ($structure < 100 && $words > 0) {
            $feedback[] = 'Gapni bosh harf bilan boshlang va nuqta bilan tugating.';
        }
        if ($feedback === []) {
            $feedback[] = 'Yaxshi! Topshiriq bajarilgan. Bog\'lovchi so\'zlar (inoltre, tuttavia, quindi) bilan boyiting.';
        }

        return [
            'score_percent' => max(0, min(100, $score)),
            'criteria' => ['task_response' => $lengthScore, 'grammar' => $structure, 'vocabulary' => $italianScore, 'coherence' => $similarity ?? $italianScore],
            'errors' => [],
            'corrected_text' => null,
            'feedback' => implode(' ', $feedback).' (Lokal baholash — o\'qituvchi tekshiruvi tavsiya etiladi.)',
            'word_count' => $words,
            'provider' => self::NAME,
        ];
    }

    /** @return array<string, mixed> */
    public function evaluateSpeaking(string $transcript, ?string $modelAnswer, ?string $level): array
    {
        $transcript = trim($transcript);

        if ($transcript === '') {
            return [
                'score_percent' => 0,
                'criteria' => ['fluency' => 0, 'pronunciation' => 0, 'grammar' => 0, 'vocabulary' => 0, 'task_response' => 0],
                'transcript' => null,
                'errors' => [],
                'feedback' => 'Ovozli javob transkripti yo\'q. Mikrofon ruxsatini tekshiring yoki brauzer nutqni tanib olishini yoqing.',
                'provider' => self::NAME,
            ];
        }

        $words = count(preg_split('/\s+/u', $transcript) ?: []);
        $italian = $this->italianRatio($transcript);
        $similarity = $modelAnswer ? (int) round(TextSimilarity::ratio($transcript, $modelAnswer) * 100) : $italian;
        $length = min(100, (int) round($words / 12 * 100));
        $score = (int) round($similarity * 0.4 + $italian * 0.35 + $length * 0.25);

        return [
            'score_percent' => max(0, min(100, $score)),
            'criteria' => ['fluency' => $length, 'pronunciation' => $italian, 'grammar' => $similarity, 'vocabulary' => $italian, 'task_response' => $similarity],
            'transcript' => $transcript,
            'errors' => [],
            'feedback' => ($score >= 60 ? 'Yaxshi javob. ' : 'Javob qisqa yoki namunadan uzoq. ').($modelAnswer ? "Namunaviy javob: «{$modelAnswer}». " : '').'(Lokal baholash.)',
            'provider' => self::NAME,
        ];
    }

    public function monthlySummary(array $data, string $studentName): string
    {
        $skills = collect($data['skills'] ?? [])->filter(fn ($v) => $v > 0);
        $best = $skills->sortDesc()->keys()->first();
        $weak = $skills->sort()->keys()->first();
        $tests = $data['tests'] ?? [];

        return sprintf(
            '%s shu oy %d ta test topshirdi (o\'rtacha %s%%), davomat %s%%. Eng kuchli ko\'nikma — %s, e\'tibor talab qiladigani — %s. Keyingi oy uchun tavsiya: har kuni 15 daqiqa %s mashqi va kartochkalar bilan lug\'at o\'yini.',
            $studentName,
            $tests['count'] ?? 0,
            $tests['average_percent'] ?? 0,
            $data['attendance']['percent'] ?? 0,
            $best ? config("academy.skills.{$best}", $best) : '—',
            $weak ? config("academy.skills.{$weak}", $weak) : '—',
            $weak ? config("academy.skills.{$weak}", $weak) : 'tinglash',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Yordamchilar */
    /* ------------------------------------------------------------------ */

    private function content(string $level): array
    {
        $levels = ItalianContent::levels();

        if (isset($levels[$level])) {
            return $levels[$level];
        }

        $ordinal = Level::ordinal($level);
        $nearest = collect(array_keys($levels))->sortBy(fn ($l) => abs(Level::ordinal($l) - $ordinal))->first();

        return $levels[$nearest ?? 'A1'];
    }

    private function bankQuestions(array $c, string $skill): array
    {
        $rows = [];

        switch ($skill) {
            case 'listening':
                foreach ($c['listening']['questions'] as [$body, $type, $answer]) {
                    $rows[] = $this->question($body, $type, $answer);
                }
                break;
            case 'reading':
                foreach ($c['reading']['questions'] as [$body, $type, $answer]) {
                    $rows[] = $this->question($body, $type, $answer);
                }
                break;
            case 'writing':
                foreach ($c['writing']['gaps'] as [$body, $answer]) {
                    $rows[] = $this->question($body, 'gap', $answer);
                }
                [$essay, $model, $min] = $c['writing']['essay'];
                $rows[] = ['body' => $essay, 'type' => 'essay', 'answer_mode' => 'written', 'score' => 5, 'model_answer' => $model, 'meta' => ['min_words' => $min], 'options' => []];
                break;
            case 'speaking':
                foreach ($c['speaking'] as [$q, $model]) {
                    $rows[] = ['body' => $q, 'type' => 'speaking', 'answer_mode' => 'voice', 'score' => 5, 'model_answer' => $model, 'options' => []];
                }
                break;
            case 'grammar':
                foreach ($c['writing']['gaps'] as [$body, $answer]) {
                    $rows[] = $this->question($body, 'gap', $answer);
                }
                break;
            case 'vocabulary':
                foreach ($c['vocabulary']['cards'] as $card) {
                    $rows[] = $this->vocabChoice($card, $c['vocabulary']['cards']);
                }
                break;
        }

        return $rows;
    }

    private function extraQuestion(string $level, string $track, string $skill, int $index): ?array
    {
        $cards = VocabDeck::where('level', $level)->whereIn('track', array_unique([$track, 'general']))
            ->with('cards')->get()->flatMap->cards;

        if ($cards->count() < 4) {
            $bank = $this->content($level)['vocabulary']['cards'];

            return isset($bank[$index % count($bank)]) ? $this->vocabChoice($bank[$index % count($bank)], $bank) : null;
        }

        /** @var VocabCard $card */
        $card = $cards[$index % $cards->count()];
        $others = $cards->where('id', '!=', $card->id)->shuffle()->take(3);

        if (in_array($skill, ['writing', 'grammar', 'speaking'], true)) {
            return [
                'body' => "Traduci in italiano: «{$card->translation('uz')}»",
                'type' => 'short_text',
                'answer_mode' => 'written',
                'score' => 1,
                'correct_answer' => $card->word,
                'similarity_threshold' => 0.85,
                'options' => [],
            ];
        }

        return [
            'body' => "Che cosa significa «{$card->word}»?",
            'type' => 'single_choice',
            'answer_mode' => 'written',
            'score' => 1,
            'options' => $others->map(fn ($o) => ['body' => $o->translation('uz'), 'is_correct' => false])
                ->push(['body' => $card->translation('uz'), 'is_correct' => true])->shuffle()->values()->all(),
        ];
    }

    private function vocabChoice(array $card, array $all): array
    {
        [$word, $uz] = $card;
        $others = array_values(array_filter($all, fn ($c) => $c[0] !== $word));
        shuffle($others);
        $options = array_map(fn ($c) => ['body' => $c[1], 'is_correct' => false], array_slice($others, 0, 3));
        $options[] = ['body' => $uz, 'is_correct' => true];
        shuffle($options);

        return ['body' => "Che cosa significa «{$word}»?", 'type' => 'single_choice', 'answer_mode' => 'written', 'score' => 1, 'correct_answer' => $uz, 'explanation' => "«{$word}» — {$uz}. Misol: {$card[4]}", 'options' => $options];
    }

    private function question(string $body, string $type, mixed $answer): array
    {
        $q = ['body' => $body, 'type' => $type, 'answer_mode' => 'written', 'score' => $type === 'multiple_choice' ? 2 : 1, 'options' => []];

        if (is_array($answer)) {
            $q['options'] = collect($answer)->map(fn ($ok, $text) => ['body' => $text, 'is_correct' => $ok])->values()->all();
        } else {
            $q['correct_answer'] = $answer;
        }

        return $q;
    }

    private function instructions(string $skill): string
    {
        return match ($skill) {
            'listening' => 'Audioni tinglang va savollarga javob bering. Audio cheklangan marta eshitiladi.',
            'reading' => 'Matnni o\'qing: Vero / Falso / Non dato, variant tanlash va bo\'sh joyni to\'ldirish.',
            'writing' => 'Bo\'sh joylarni to\'ldiring va insho yozing.',
            'speaking' => 'Mikrofon orqali italyancha javob bering.',
            'grammar' => 'To\'g\'ri grammatik shaklni yozing.',
            default => 'So\'zning to\'g\'ri tarjimasini tanlang.',
        };
    }

    /** Matndagi italyancha yordamchi so'zlar ulushi (0..100) */
    private function italianRatio(string $text): int
    {
        $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return 0;
        }

        $hits = count(array_filter($words, fn ($w) => in_array($w, self::ITALIAN_HINTS, true) || preg_match('/(are|ere|ire|zione|mente|ato|uto|ito|ano|ono|iamo)$/u', $w)));

        return min(100, (int) round($hits / count($words) * 250));
    }
}
