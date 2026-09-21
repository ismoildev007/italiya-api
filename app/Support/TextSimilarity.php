<?php

namespace App\Support;

/**
 * Mockupdagi `textSimilarity` — Levenshtein asosida 0..1 oralig'ida o'xshashlik.
 * Diktant (listening), gap-fill (reading/writing) va talaffuz (speaking)
 * javoblarini avtomatik tekshirishda ishlatiladi.
 */
final class TextSimilarity
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[.,!?;:\'"()«»„“”]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** 0.0 (umuman boshqa) … 1.0 (aynan bir xil). */
    public static function ratio(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' && $b === '') {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $max = max(mb_strlen($a), mb_strlen($b));
        $distance = self::levenshtein($a, $b);

        return round(1 - $distance / $max, 4);
    }

    public static function matches(string $given, string $expected, float $threshold = 0.85): bool
    {
        return self::ratio($given, $expected) >= $threshold;
    }

    /** Unicode uchun xavfsiz Levenshtein masofasi. */
    public static function levenshtein(string $a, string $b): int
    {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $la = count($a);
        $lb = count($b);

        if ($la === 0) {
            return $lb;
        }

        if ($lb === 0) {
            return $la;
        }

        $prev = range(0, $lb);

        for ($i = 1; $i <= $la; $i++) {
            $curr = [$i];

            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $curr[$j] = min($prev[$j] + 1, $curr[$j - 1] + 1, $prev[$j - 1] + $cost);
            }

            $prev = $curr;
        }

        return $prev[$lb];
    }
}
