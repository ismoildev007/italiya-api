<?php

namespace App\Support;

use App\Models\TestQuestion;

/** config('academy.features') bayroqlari uchun qisqa yordamchi. */
final class Feature
{
    public static function ai(): bool
    {
        return (bool) config('academy.features.ai', false);
    }

    public static function audio(): bool
    {
        return (bool) config('academy.features.audio', false);
    }

    public static function duel(): bool
    {
        return (bool) config('academy.features.duel', true);
    }

    /** Ruxsat etilgan ko'nikmalar (tinglash o'chirilgan bo'lsa ro'yxatda yo'q) */
    public static function skills(): array
    {
        $skills = array_keys(config('academy.skills', []));

        return self::audio() ? array_values(array_unique([...$skills, 'listening'])) : $skills;
    }

    /** Ruxsat etilgan savol turlari */
    public static function questionTypes(): array
    {
        $types = TestQuestion::TYPES;

        return self::audio() ? $types : array_values(array_diff($types, [TestQuestion::TYPE_DICTATION]));
    }

    public static function roundTypes(): array
    {
        return self::audio() ? ['it-to-tr', 'tr-to-it', 'voice', 'typing', 'listening'] : ['it-to-tr', 'tr-to-it', 'typing'];
    }
}
