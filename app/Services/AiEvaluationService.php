<?php

namespace App\Services;

use App\Models\TestAnswer;
use App\Models\TestQuestion;
use App\Services\Ai\AiManager;
use App\Services\Ai\LocalAi;
use App\Support\TextSimilarity;
use Throwable;

/**
 * Writing va Speaking javoblarini AI (Gemini) orqali tahlil qiladi:
 * ball (0–100), mezonlar, xatolar, tuzatilgan matn va o'zbekcha fikr-mulohaza.
 * Natija `test_answers.ai_*` ustunlariga yoziladi; o'qituvchi keyin o'zgartira oladi.
 */
class AiEvaluationService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly LocalAi $local,
    ) {}

    /** Har doim mavjud: tashqi AI bo'lmasa lokal evristik baholash ishlaydi. */
    public function isAvailable(): bool
    {
        return true;
    }

    public function remoteAvailable(): bool
    {
        return $this->ai->remoteAvailable('evaluator');
    }

    /**
     * Javobni baholaydi va yozib qo'yadi. Xatolik bo'lsa null (qo'lda tekshiruvga qoladi).
     */
    public function evaluate(TestAnswer $answer, ?string $level = null, string $feedbackLanguage = 'uz'): ?TestAnswer
    {
        $question = $answer->question ?? $answer->question()->first();

        if (! $question) {
            return null;
        }

        $isSpeaking = $question->type === TestQuestion::TYPE_SPEAKING || $question->answer_mode === TestQuestion::MODE_VOICE;
        $result = null;
        $provider = LocalAi::NAME;

        if ($this->remoteAvailable()) {
            try {
                $result = $isSpeaking
                    ? $this->evaluateSpeaking($question, $answer, $level, $feedbackLanguage)
                    : $this->evaluateWriting($question, (string) $answer->effectiveText(), $level, $feedbackLanguage);
                $provider = $this->ai->for('evaluator')->name();
            } catch (Throwable $e) {
                report($e);
                $result = null;
            }
        }

        if (! is_array($result) || ! isset($result['score_percent'])) {
            $result = $isSpeaking
                ? $this->local->evaluateSpeaking((string) ($answer->transcript ?: $answer->answer_text), $question->model_answer, $level)
                : $this->local->evaluateWriting((string) $answer->effectiveText(), $question->model_answer, $question->meta['min_words'] ?? null, $level);
            $provider = LocalAi::NAME;
        }

        $percent = max(0, min(100, (float) ($result['score_percent'] ?? 0)));
        $maxScore = (float) $question->score;

        $answer->update([
            'ai_score' => round($percent / 100 * $maxScore, 2),
            'ai_feedback' => $result,
            'ai_provider' => $provider,
            'ai_evaluated_at' => now(),
            'transcript' => $answer->transcript ?: ($result['transcript'] ?? null),
        ]);

        return $answer->fresh();
    }

    /** @return array<string, mixed>|null */
    public function evaluateWriting(TestQuestion $question, string $text, ?string $level, string $lang = 'uz'): ?array
    {
        if (trim($text) === '') {
            return ['score_percent' => 0, 'feedback' => 'Javob yozilmagan.', 'criteria' => []];
        }

        $prompt = <<<PROMPT
        O'quvchining italyan tilidagi YOZMA javobini CEFR {$level} darajasi talablari bo'yicha bahola.

        Topshiriq: {$question->body}
        Namunaviy javob (bo'lsa): {$question->model_answer}
        O'quvchi javobi:
        \"\"\"
        {$text}
        \"\"\"

        Mezonlar (har biri 0–100): task_response (topshiriqqa mosligi), grammar, vocabulary, coherence (bog'liqlik va tuzilish).
        score_percent — umumiy ball (0–100), darajaga nisbatan.
        Fikr-mulohaza "{$lang}" tilida, qisqa va foydali. Xatolarni ro'yxat qilib, to'g'risini ko'rsat.

        JSON:
        {
          "score_percent": 0,
          "criteria": {"task_response": 0, "grammar": 0, "vocabulary": 0, "coherence": 0},
          "errors": [{"wrong": "…", "correct": "…", "note": "…"}],
          "corrected_text": "tuzatilgan italyancha matn",
          "feedback": "umumiy fikr ({$lang})",
          "word_count": 0
        }
        PROMPT;

        return $this->ai->for('evaluator')->json($prompt, $this->system(), ['temperature' => 0.2]);
    }

    /** @return array<string, mixed>|null */
    public function evaluateSpeaking(TestQuestion $question, TestAnswer $answer, ?string $level, string $lang = 'uz'): ?array
    {
        $transcript = (string) ($answer->transcript ?: $answer->answer_text);

        $prompt = <<<PROMPT
        O'quvchining italyan tilidagi OG'ZAKI javobini CEFR {$level} darajasi bo'yicha bahola.

        Savol: {$question->body}
        Namunaviy javob (bo'lsa): {$question->model_answer}
        Transkript (STT): {$transcript}

        Agar audio berilgan bo'lsa — talaffuz (pronunciation) va ravonlikni (fluency) audiodan bahola; bo'lmasa transkriptga tayanib taxminiy bahola.
        Mezonlar (0–100): fluency, pronunciation, grammar, vocabulary, task_response.
        score_percent — umumiy ball (0–100).
        Fikr-mulohaza "{$lang}" tilida. Noto'g'ri talaffuz qilingan yoki xato so'zlarni ko'rsat.

        JSON:
        {
          "score_percent": 0,
          "criteria": {"fluency": 0, "pronunciation": 0, "grammar": 0, "vocabulary": 0, "task_response": 0},
          "transcript": "audio bo'yicha aniq transkript (bo'lsa)",
          "errors": [{"wrong": "…", "correct": "…", "note": "…"}],
          "feedback": "umumiy fikr ({$lang})"
        }
        PROMPT;

        $client = $this->ai->for('evaluator');

        if ($answer->audio_path) {
            return $client->jsonWithAudio($prompt, $answer->audio_path, $this->system());
        }

        if (trim($transcript) === '') {
            return ['score_percent' => 0, 'feedback' => 'Ovozli javob yo\'q.', 'criteria' => []];
        }

        return $client->json($prompt, $this->system(), ['temperature' => 0.2]);
    }

    /**
     * Talaffuz o'yini: aytilgan so'z to'g'rimi va qanday xatolar bor.
     *
     * @return array{correct: bool, similarity: float, feedback: ?string}
     */
    public function checkPronunciation(string $expected, string $transcript, string $lang = 'uz'): array
    {
        $similarity = TextSimilarity::ratio($transcript, $expected);

        return [
            'correct' => $similarity >= 0.8,
            'similarity' => $similarity,
            'feedback' => $similarity >= 0.8 ? null : "Kutilgan: «{$expected}», eshitildi: «{$transcript}»",
        ];
    }

    /**
     * Oylik hisobot uchun o'zbekcha xulosa (AI bo'lmasa — shablon matn).
     */
    public function monthlySummary(array $data, string $studentName, string $lang = 'uz'): string
    {
        $fallback = sprintf(
            '%s: %d ta test topshirdi, o\'rtacha natija %s%%. Ko\'nikmalar: %s.',
            $studentName,
            $data['tests']['count'] ?? 0,
            $data['tests']['average_percent'] ?? 0,
            collect($data['skills'] ?? [])->map(fn ($v, $k) => config("academy.skills.{$k}", $k).' '.$v.'%')->implode(', ') ?: '—',
        );

        $fallback = $this->local->monthlySummary($data, $studentName);

        if (! $this->remoteAvailable()) {
            return $fallback;
        }

        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);

            return trim($this->ai->for('evaluator')->complete(
                "O'quvchi {$studentName} ning shu oylik italyan tili o'zlashtirish ma'lumotlari (JSON): {$json}\n\n"
                ."Ota-onaga yuboriladigan qisqa (5–7 gap), samimiy va aniq tahlil yoz ({$lang} tilida): kuchli tomonlar, "
                .'zaif ko\'nikmalar, keyingi oy uchun tavsiya. Markdown ishlatma.',
                $this->system(),
                ['temperature' => 0.5, 'max_tokens' => 800],
            )) ?: $fallback;
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    private function system(): string
    {
        return 'Sen italyan tili bo\'yicha CEFR ekspert-baholovchisan. Adolatli, izchil va darajaga mos baho berasan. Faqat so\'ralgan formatda javob qaytarasan.';
    }
}
