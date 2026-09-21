<?php

namespace Database\Seeders\Data;

/**
 * IELTS uslubidagi italyan tili testlari — ItalianContent asosida har bir daraja uchun
 * bo'limlar (listening / reading / writing / speaking) tuziladi.
 * Real ishda savollar AI orqali generatsiya qilinadi va o'qituvchi tahrirlaydi.
 */
class TestBank
{
    public const TRANSCRIPTS = [
        'Mi chiamo Ali, ho ventidue anni e sono di Tashkent. Studio italiano da un anno.',
        'Nel tempo libero mi piace leggere e ascoltare musica italiana.',
        'Ieri sono andato al mercato con mia madre e abbiamo comprato la frutta.',
        'Il mio lavoro ideale sarebbe fare l\'interprete perché amo le lingue.',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function templates(): array
    {
        $templates = [];

        foreach (ItalianContent::levels() as $level => $c) {
            // 1) To'liq mock (4 bo'lim, 1 urinish, audio 1 marta)
            $templates[] = [
                'level' => $level,
                'kind' => 'mock',
                'title' => "{$level} Mock imtihoni — {$c['topic']}",
                'topic' => $c['topic'],
                'difficulty' => 'medium',
                'generated_by' => 'ai',
                'duration_minutes' => 90,
                'sections' => self::fullSections($c),
            ];

            // 2) Mashq: listening + reading (cheksiz urinish, audio 2 marta)
            $templates[] = [
                'level' => $level,
                'kind' => 'practice',
                'title' => "{$level} Mashq: tinglash va o'qish",
                'topic' => $c['topic'],
                'difficulty' => 'easy',
                'generated_by' => 'ai',
                'duration_minutes' => 40,
                'sections' => array_slice(self::fullSections($c), 0, 2),
            ];

            // 3) Uy ishi: writing + speaking (AI baholaydi)
            $templates[] = [
                'level' => $level,
                'kind' => 'homework',
                'title' => "{$level} Uy ishi: yozish va gapirish",
                'topic' => $c['topic'],
                'difficulty' => 'medium',
                'generated_by' => 'manual',
                'duration_minutes' => 45,
                'sections' => array_slice(self::fullSections($c), 2, 2),
            ];
        }

        return $templates;
    }

    private static function fullSections(array $c): array
    {
        $listening = [];
        foreach ($c['listening']['questions'] as [$body, $type, $answer]) {
            $listening[] = self::question($body, $type, $answer, 'listening');
        }

        $reading = [];
        foreach ($c['reading']['questions'] as [$body, $type, $answer]) {
            $reading[] = self::question($body, $type, $answer, 'reading');
        }

        $writing = [];
        foreach ($c['writing']['gaps'] as [$body, $answer]) {
            $writing[] = self::question($body, 'gap', $answer, 'writing');
        }
        [$essayBody, $model, $minWords] = $c['writing']['essay'];
        $writing[] = ['body' => $essayBody, 'type' => 'essay', 'skill' => 'writing', 'answer_mode' => 'written', 'score' => 5, 'model_answer' => $model, 'meta' => ['min_words' => $minWords]];

        $speaking = [];
        foreach ($c['speaking'] as [$q, $model]) {
            $speaking[] = ['body' => $q, 'type' => 'speaking', 'skill' => 'speaking', 'answer_mode' => 'voice', 'score' => 5, 'model_answer' => $model];
        }

        return [
            ['skill' => 'listening', 'title' => 'Ascolto', 'instructions' => 'Audioni tinglang va savollarga javob bering. Audio cheklangan marta eshitiladi.', 'media_script' => $c['listening']['script'], 'time_limit_minutes' => 15, 'questions' => $listening],
            ['skill' => 'reading', 'title' => 'Lettura: '.$c['reading']['title'], 'instructions' => 'Matnni o\'qing. Vero / Falso / Non dato, variant tanlash va bo\'sh joyni to\'ldirish.', 'passage' => $c['reading']['passage'], 'time_limit_minutes' => 20, 'questions' => $reading],
            ['skill' => 'writing', 'title' => 'Scrittura', 'instructions' => 'Bo\'sh joylarni to\'ldiring va insho yozing. AI grammatika, so\'z boyligi va tuzilishni baholaydi.', 'time_limit_minutes' => 30, 'questions' => $writing],
            ['skill' => 'speaking', 'title' => 'Parlato', 'instructions' => 'Mikrofon orqali javob bering. Ovoz matnga o\'giriladi (OpenAI) va talaffuz tahlil qilinadi (Gemini).', 'time_limit_minutes' => 10, 'questions' => $speaking],
        ];
    }

    private static function question(string $body, string $type, mixed $answer, string $skill): array
    {
        $q = ['body' => $body, 'type' => $type, 'skill' => $skill, 'answer_mode' => 'written', 'score' => $type === 'multiple_choice' ? 2 : 1];

        if (is_array($answer)) {
            $q['options'] = collect($answer)->map(fn ($ok, $text) => ['body' => $text, 'is_correct' => $ok])->values()->all();
        } else {
            $q['correct_answer'] = $answer;
        }

        return $q;
    }
}
