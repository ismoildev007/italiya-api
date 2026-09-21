<?php

namespace App\Support;

/**
 * CEFR darajalari bilan ishlash: A1 → A2 → B1 → B2 → C1 → C2.
 */
final class Level
{
    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(config('academy.levels', []));
    }

    public static function isValid(?string $level): bool
    {
        return $level !== null && in_array($level, self::all(), true);
    }

    public static function ordinal(?string $level): int
    {
        return (int) config("academy.levels.{$level}.ordinal", 0);
    }

    public static function title(?string $level): string
    {
        return (string) config("academy.levels.{$level}.title", $level ?? '—');
    }

    public static function next(?string $level): ?string
    {
        $levels = self::all();
        $index = array_search($level, $levels, true);

        return $index === false ? $levels[0] ?? null : ($levels[$index + 1] ?? null);
    }

    public static function previous(?string $level): ?string
    {
        $levels = self::all();
        $index = array_search($level, $levels, true);

        return $index === false || $index === 0 ? null : $levels[$index - 1];
    }

    /** Natijaga ko'ra daraja tavsiya qilish (placement test uchun). */
    public static function fromPercent(float $percent): string
    {
        return match (true) {
            $percent >= 95 => 'C2',
            $percent >= 85 => 'C1',
            $percent >= 70 => 'B2',
            $percent >= 55 => 'B1',
            $percent >= 35 => 'A2',
            default => 'A1',
        };
    }

    /** Sertifikatdagi baho yorlig'i (italyancha). */
    public static function gradeLabel(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'Eccellente',
            $percent >= 80 => 'Ottimo',
            $percent >= 70 => 'Buono',
            $percent >= 60 => 'Sufficiente',
            default => 'Insufficiente',
        };
    }
}
