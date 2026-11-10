<?php

namespace App\Support;

use App\Models\TestQuestion;

/**
 * Test savollari Excel/CSV faylini savollar ro'yxatiga aylantiradi (bazaga yozilmaydi —
 * o'qituvchi ro'yxatda to'g'ri javobni belgilab, keyin saqlaydi).
 *
 * Standart format (sarlavha qatori ixtiyoriy, `№` ustuni tashlanadi):
 *   № | Savol | A | B | C | D | To'g'ri javob | Izoh | Ball
 *
 * - Variant ustunlari: A, B, C, D, E… yoki "1-variant", "Variant 2", "Javob 3".
 * - "To'g'ri javob" ustuni ixtiyoriy: harf (A), raqam (2), variant matni yoki bir nechtasi ("A, C") —
 *   bo'sh qolsa o'qituvchi interfeysda belgilaydi.
 * - Variantlarsiz qator: "to'g'ri javob" true/false bo'lsa `true_false`, matn bo'lsa `short_text`.
 */
final class TestSheetParser
{
    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{title: ?string, questions: array<int, array<string, mixed>>, unmarked: int}
     */
    public static function parse(array $rows): array
    {
        $headerIndex = self::findHeaderRow($rows);
        $title = null;
        $map = ['body' => 0, 'options' => [1, 2, 3, 4], 'correct' => 5, 'explanation' => 6, 'score' => 7];

        if ($headerIndex !== null) {
            foreach (array_slice($rows, 0, $headerIndex) as $row) {
                $text = trim(implode(' ', array_filter(array_map('trim', $row))));

                if ($text !== '' && $title === null) {
                    $title = $text;
                }
            }

            $map = self::mapFromHeader($rows[$headerIndex]);
            $rows = array_slice($rows, $headerIndex + 1);
        } elseif (self::looksNumbered($rows)) {
            $map = ['body' => 1, 'options' => [2, 3, 4, 5], 'correct' => 6, 'explanation' => 7, 'score' => 8];
        }

        $questions = [];
        $unmarked = 0;

        foreach ($rows as $row) {
            $body = self::cell($row, $map['body']);

            if ($body === '') {
                continue;
            }

            $options = array_values(array_filter(array_map(fn ($i) => self::cell($row, $i), $map['options']), fn ($v) => $v !== ''));
            $correctRaw = self::cell($row, $map['correct']);
            $explanation = self::cell($row, $map['explanation']) ?: null;
            $score = (float) (self::cell($row, $map['score']) ?: 1) ?: 1;

            if ($options !== []) {
                $correctIndexes = self::resolveCorrect($correctRaw, $options);
                $type = count($correctIndexes) > 1 ? TestQuestion::TYPE_MULTIPLE : TestQuestion::TYPE_SINGLE;

                if ($correctIndexes === []) {
                    $unmarked++;
                }

                $questions[] = [
                    'body' => $body,
                    'type' => $type,
                    'score' => $score,
                    'explanation' => $explanation,
                    'correct_answer' => null,
                    'options' => array_map(fn ($text, $i) => ['body' => $text, 'is_correct' => in_array($i, $correctIndexes, true)], $options, array_keys($options)),
                ];

                continue;
            }

            $lower = mb_strtolower($correctRaw);
            $isBool = in_array($lower, ['true', 'false', 'ng', 'to\'g\'ri', 'noto\'g\'ri', 'ha', 'yo\'q', 'vero', 'falso', 'да', 'нет'], true);

            $questions[] = [
                'body' => $body,
                'type' => $isBool ? TestQuestion::TYPE_TRUE_FALSE : TestQuestion::TYPE_SHORT_TEXT,
                'score' => $score,
                'explanation' => $explanation,
                'correct_answer' => $isBool ? self::normalizeBool($lower) : ($correctRaw ?: null),
                'options' => [],
            ];

            if (! $isBool && $correctRaw === '') {
                $unmarked++;
            }
        }

        return ['title' => $title, 'questions' => $questions, 'unmarked' => $unmarked];
    }

    /** Sarlavha qatori: "savol/question/domanda/вопрос" ustuni bo'lgan qator */
    private static function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 15, true) as $index => $row) {
            foreach ($row as $cell) {
                if (preg_match('/^(savol|question|domanda|вопрос|test)/iu', trim((string) $cell))) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $header
     * @return array{body: int, options: array<int, int>, correct: ?int, explanation: ?int, score: ?int}
     */
    private static function mapFromHeader(array $header): array
    {
        $map = ['body' => null, 'options' => [], 'correct' => null, 'explanation' => null, 'score' => null];

        foreach ($header as $i => $raw) {
            $h = mb_strtolower(trim((string) $raw));

            if ($h === '' || preg_match('/^(№|#|n|no|t\/r|id)\.?$/u', $h)) {
                continue;
            }

            match (true) {
                $map['body'] === null && (bool) preg_match('/^(savol|question|domanda|вопрос|test)/u', $h) => $map['body'] = $i,
                (bool) preg_match('/to.?g.?ri|correct|right|javob\s*\(|^javob$|answer|risposta|ответ|правиль/u', $h) => $map['correct'] = $i,
                (bool) preg_match('/izoh|explanation|comment|spiegazione|поясн|коммент/u', $h) => $map['explanation'] = $i,
                (bool) preg_match('/^ball|score|punti|балл|очк/u', $h) => $map['score'] = $i,
                (bool) preg_match('/^[a-h]$|variant|option|opzione|вариант|^javob\s*\d/u', $h) => $map['options'][] = $i,
                default => null,
            };
        }

        $map['body'] ??= 0;

        if ($map['options'] === []) {
            // Sarlavhada variant ustunlari nomlanmagan — savoldan keyingi 4 ustun
            $map['options'] = [$map['body'] + 1, $map['body'] + 2, $map['body'] + 3, $map['body'] + 4];
        }

        return $map;
    }

    /**
     * "A", "a, c", "2", "Grazie" ko'rinishidagi to'g'ri javobni variant indekslariga o'giradi.
     *
     * @param  array<int, string>  $options
     * @return array<int, int>
     */
    private static function resolveCorrect(string $raw, array $options): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $indexes = [];

        foreach (preg_split('/\s*[,;\/|]\s*/u', trim($raw)) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^[a-h]$/i', $part)) {
                $indexes[] = ord(strtoupper($part)) - 65;
            } elseif (preg_match('/^\d+$/', $part)) {
                $indexes[] = (int) $part - 1;
            } else {
                foreach ($options as $i => $option) {
                    if (mb_strtolower($option) === mb_strtolower($part)) {
                        $indexes[] = $i;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($indexes, fn ($i) => $i >= 0 && $i < count($options))));
    }

    private static function normalizeBool(string $value): string
    {
        return match ($value) {
            'true', 'to\'g\'ri', 'ha', 'vero', 'да' => 'true',
            'ng' => 'ng',
            default => 'false',
        };
    }

    private static function looksNumbered(array $rows): bool
    {
        $sample = array_slice(array_filter($rows, fn ($r) => trim(implode('', $r)) !== ''), 0, 5);

        return $sample !== [] && collect($sample)->every(fn ($r) => preg_match('/^\d+\.?$/', trim((string) ($r[0] ?? ''))));
    }

    private static function cell(array $row, ?int $index): string
    {
        return $index === null ? '' : trim((string) ($row[$index] ?? ''));
    }
}
