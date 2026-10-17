<?php

namespace App\Services;

use App\Models\SkillProgress;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestTemplate;
use App\Services\Ai\AiManager;
use App\Services\Ai\LocalAi;
use App\Support\Feature;
use App\Support\Level;

/**
 * AI orqali IELTS uslubidagi italyan tili testini generatsiya qiladi.
 *
 * Jarayon:
 *  1. O'qituvchi daraja (A1..C2), ko'nikmalar (listening/reading/writing/speaking/…),
 *     mavzu, savollar soni va test turini (practice/homework/test/mock/placement) kiritadi
 *  2. AI (config: services.ai.generator — gemini/openai/anthropic) bo'limlar va savollarni
 *     qaytaradi; qiyinlik o'quvchi/guruhning oldingi natijalariga moslashadi
 *  3. Natija BAZAGA YOZILMAYDI — o'qituvchi tahrirlaydi, keyin TestService::saveTemplate
 *  4. Listening bo'limi uchun `media_script` qaytadi — saqlangandan keyin OpenAI TTS
 *     orqali audio generatsiya qilinadi (TestService::generateAudio)
 */
class AiTestGeneratorService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly LocalAi $local,
    ) {}

    /**
     * @return array{title:string, description:?string, sections:array, difficulty:string, prompt:string, model:string, provider:string}
     */
    public function generate(array $params): array
    {
        $level = $params['level'];
        $skills = array_values(array_intersect(array_unique($params['skills'] ?? config('academy.core_skills')), Feature::skills()));
        $count = (int) ($params['question_count'] ?? 5);
        $kind = $params['kind'] ?? TestTemplate::KIND_PRACTICE;
        $track = $params['track'] ?? 'general';
        $topic = $params['topic'] ?? null;
        $translation = $params['translation_language'] ?? 'uz';

        $difficulty = $params['difficulty'] ?? null;

        if (! $difficulty || $difficulty === 'adaptive') {
            $difficulty = $this->adaptiveDifficulty($level, $params['student_id'] ?? null, $params['class_id'] ?? null);
        }

        $prompt = $this->buildPrompt($level, $track, $skills, $count, $kind, $topic, $difficulty, $translation, $params['instructions'] ?? null);
        $provider = LocalAi::NAME;
        $model = LocalAi::MODEL;
        $sections = [];
        $result = [];

        // Tashqi AI: kalit bo'lsa urinib ko'ramiz; xato bo'lsa lokal bankka o'tamiz (hech qachon 5xx qaytarmaydi)
        if ($this->ai->remoteAvailable('generator')) {
            try {
                $client = $this->ai->for('generator');
                $result = $client->json($prompt, $this->systemPrompt(), ['max_tokens' => 12000, 'temperature' => 0.6]);
                $sections = $this->normalizeSections($result['sections'] ?? [], $skills, $count);

                if ($sections !== []) {
                    $provider = $client->name();
                    $model = $client->model();
                }
            } catch (\Throwable $e) {
                report($e);
                $sections = [];
            }
        }

        if ($sections === []) {
            $result = $this->local->generateTest($level, $skills, $count, $kind, $topic, $track, $translation);
            $sections = $this->normalizeSections($result['sections'], $skills, $count);
        }

        return [
            'title' => $result['title'] ?? ($topic ? "{$level} — {$topic}" : "{$level} test"),
            'description' => $result['description'] ?? null,
            'level' => $level,
            'track' => $track,
            'kind' => $kind,
            'sections' => $sections,
            'difficulty' => $difficulty,
            'prompt' => $prompt,
            'model' => $model,
            'provider' => $provider,
        ];
    }

    /**
     * O'quvchining (yoki guruhning) oxirgi 3 oydagi natijalariga qarab qiyinlik.
     */
    public function adaptiveDifficulty(string $level, ?int $studentId = null, ?int $classId = null): string
    {
        $query = TestAttempt::query()
            ->whereIn('status', [TestAttempt::STATUS_GRADED, TestAttempt::STATUS_SUBMITTED])
            ->where('created_at', '>=', now()->subMonths(3))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when(! $studentId && $classId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('class_id', $classId)));

        $average = (float) $query->avg('percent');

        if ($average <= 0 && $studentId) {
            $average = (float) SkillProgress::where('student_id', $studentId)->where('level', $level)->avg('percent');
        }

        if ($average <= 0) {
            return 'medium';
        }

        return match (true) {
            $average >= 85 => 'hard',
            $average >= 60 => 'medium',
            default => 'easy',
        };
    }

    private function systemPrompt(): string
    {
        return 'Sen italyan tili bo\'yicha tajribali o\'qituvchi va CEFR/IELTS uslubidagi imtihon tuzuvchisan. '
            .'Barcha til materiali (matnlar, savollar, variantlar, namunaviy javoblar) ITALYAN tilida bo\'ladi, '
            .'ko\'rsatmalar va tarjimalar so\'ralgan tilda. Faqat to\'g\'ri JSON qaytarasan.';
    }

    private function buildPrompt(string $level, string $track, array $skills, int $count, string $kind, ?string $topic, string $difficulty, string $translation, ?string $instructions): string
    {
        $levelTitle = Level::title($level);
        $trackTitle = config("academy.tracks.{$track}", $track);
        $skillList = implode(', ', $skills);
        $translationName = config("academy.translation_languages.{$translation}", $translation);
        $topicLine = $topic ? "Mavzu: {$topic}" : 'Mavzu: darajaga mos umumiy mavzu';
        $extra = $instructions ? "Qo'shimcha ko'rsatma: {$instructions}" : '';

        $kindLine = match ($kind) {
            TestTemplate::KIND_MOCK => 'Bu MOCK (sinov imtihoni) — real imtihon formatiga maksimal yaqin, har bo\'lim to\'liq.',
            TestTemplate::KIND_PLACEMENT => 'Bu PLACEMENT (daraja aniqlash) testi — savollar osondan qiyinga o\'sib boradi.',
            TestTemplate::KIND_HOMEWORK => 'Bu UY ISHI — mustaqil bajarish uchun, izohlar batafsil.',
            TestTemplate::KIND_TEST => 'Bu NAZORAT TESTI — baholash uchun, izohlar qisqa.',
            default => 'Bu MASHQ — o\'rganish uchun, har savolda o\'rgatuvchi izoh bo\'lsin.',
        };

        return <<<PROMPT
        Italyan tili testi tuz.

        Daraja (CEFR): {$level} — {$levelTitle}
        Yo'nalish: {$trackTitle} (so'z boyligi shu yo'nalishga mos bo'lsin)
        {$topicLine}
        Bo'limlar (har biri alohida section): {$skillList}
        Har bir bo'limda savollar soni: {$count} ta (aynan shuncha)
        Qiyinlik: {$difficulty}
        Tarjima/ko'rsatma tili: {$translationName} (kod: {$translation})
        {$kindLine}
        {$extra}

        Bo'lim qoidalari:
        - listening: `media_script` — 60–180 so'zli italyancha monolog/dialog (audio shu matndan generatsiya qilinadi). Savollar: single_choice, true_false, dictation (audiodan eshitilgan gapni yozish, correct_answer = aynan gap) aralash.
        - reading: `passage` — darajaga mos 80–300 so'zli italyancha matn. Savollar: true_false (correct_answer: "true" | "false" | "ng" — Not Given), single_choice, gap (matndan bitta so'z, correct_answer = so'z).
        - writing: gap (to'liq gapdagi bitta so'z tashlab ketilgan, body ichida _____ bo'lsin, correct_answer = tushirilgan so'z) va essay (Scrivi… ko'rsatma, `model_answer` namunaviy javob, `meta.min_words`).
        - speaking: speaking turidagi savollar (body — italyancha savol, `model_answer` — namunaviy javob), answer_mode = voice.
        - grammar: single_choice / multiple_choice / gap.
        - vocabulary: single_choice (so'zning tarjimasi yoki sinonimi), gap.

        Har bir savolda `translations` obyektida savolning "{$translation}" tilidagi tarjimasi bo'lsin.
        Variantli savollarda 3–4 variant, faqat bittasi (multiple_choice da bir nechtasi) to'g'ri.
        `score`: variantli/gap/dictation — 1, essay — 5, speaking — 5.

        Faqat quyidagi JSON strukturasini qaytar:
        {
          "title": "test nomi",
          "description": "qisqa tavsif ({$translation} tilida)",
          "sections": [
            {
              "skill": "listening | reading | writing | speaking | grammar | vocabulary",
              "title": "bo'lim nomi",
              "instructions": "ko'rsatma ({$translation})",
              "passage": "reading uchun matn yoki null",
              "media_script": "listening uchun audio matni yoki null",
              "questions": [
                {
                  "body": "savol matni (italyancha)",
                  "type": "single_choice | multiple_choice | true_false | short_text | gap | dictation | essay | speaking",
                  "answer_mode": "written | voice",
                  "score": 1,
                  "correct_answer": "gap/dictation/true_false uchun javob, aks holda null",
                  "model_answer": "essay/speaking uchun namunaviy javob yoki null",
                  "explanation": "izoh ({$translation}) yoki null",
                  "translations": {"{$translation}": "savol tarjimasi"},
                  "options": [{"body": "variant", "is_correct": true}],
                  "meta": {"min_words": 60}
                }
              ]
            }
          ]
        }
        PROMPT;
    }

    /**
     * AI javobini validatsiya qilib, savol tuzilishini standartlashtiradi.
     */
    private function normalizeSections(array $sections, array $skills, int $count): array
    {
        $result = [];

        foreach ($sections as $index => $section) {
            $skill = $section['skill'] ?? ($skills[$index] ?? null);

            if (! $skill || ! array_key_exists($skill, config('academy.skills'))) {
                continue;
            }

            $questions = [];

            foreach (array_slice($section['questions'] ?? [], 0, max($count, 1)) as $qi => $q) {
                if (empty($q['body'])) {
                    continue;
                }

                $type = in_array($q['type'] ?? '', TestQuestion::TYPES, true) ? $q['type'] : 'single_choice';
                $mode = $type === 'speaking' ? 'voice' : (($q['answer_mode'] ?? 'written') === 'voice' ? 'voice' : 'written');

                $options = collect($q['options'] ?? [])
                    ->filter(fn ($o) => ! empty($o['body']))
                    ->map(fn ($o, $i) => ['order' => $i + 1, 'body' => (string) $o['body'], 'is_correct' => (bool) ($o['is_correct'] ?? false)])
                    ->values()->all();

                if ($type === 'true_false') {
                    $options = [];
                    $q['correct_answer'] = in_array(strtolower((string) ($q['correct_answer'] ?? '')), ['true', 'false', 'ng'], true)
                        ? strtolower($q['correct_answer']) : 'true';
                }

                $questions[] = [
                    'order' => $qi + 1,
                    'body' => (string) $q['body'],
                    'type' => $type,
                    'skill' => $skill,
                    'answer_mode' => $mode,
                    'score' => (float) ($q['score'] ?? ($type === 'essay' || $type === 'speaking' ? 5 : 1)),
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'model_answer' => $q['model_answer'] ?? null,
                    'explanation' => $q['explanation'] ?? null,
                    'translations' => is_array($q['translations'] ?? null) ? $q['translations'] : null,
                    'media_script' => $q['media_script'] ?? null,
                    'similarity_threshold' => $q['similarity_threshold'] ?? null,
                    'meta' => is_array($q['meta'] ?? null) ? $q['meta'] : null,
                    'options' => $options,
                    'ai_generated' => true,
                ];
            }

            if ($questions === []) {
                continue;
            }

            $result[] = [
                'order' => count($result) + 1,
                'skill' => $skill,
                'title' => $section['title'] ?? config("academy.skills.{$skill}"),
                'instructions' => $section['instructions'] ?? null,
                'passage' => $skill === 'reading' ? ($section['passage'] ?? null) : null,
                'media_script' => $skill === 'listening' ? ($section['media_script'] ?? null) : null,
                'time_limit_minutes' => config("academy.defaults.section_minutes.{$skill}"),
                'ai_generated' => true,
                'questions' => $questions,
            ];
        }

        return $result;
    }
}
