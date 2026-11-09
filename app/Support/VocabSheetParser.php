<?php

namespace App\Support;

/**
 * Lug'at Excel/CSV faylini bir xil ko'rinishga keltiradi. Testlar va so'z o'yinlari
 * bitta shu format orqali kiritiladi (masalan `italyan_50_soz.xlsx`):
 *
 *   [sarlavha qatorlari — ixtiyoriy]
 *   № | Italiya so'zi | O'zbekcha tarjimasi | Kategoriya | [en] | [ru] | [misol]
 *
 * Sarlavha qatori avtomatik topiladi (birinchi 15 qator ichida), `№`/`#` ustuni tashlab
 * yuboriladi. Sarlavhasiz faylda ustun tartibi: so'z | tarjima | en | ru | misol | kategoriya.
 */
final class VocabSheetParser
{
    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{title: ?string, description: ?string, items: array<int, array{word: string, translations: array<string, string>, example: ?string, category: ?string}>}
     */
    public static function parse(array $rows): array
    {
        $headerIndex = self::findHeaderRow($rows);
        $title = null;
        $description = null;
        $map = ['word' => 0, 'uz' => 1, 'en' => 2, 'ru' => 3, 'example' => 4, 'category' => 5];

        if ($headerIndex !== null) {
            // Sarlavhadan oldingi 1-2 qator — fayl nomi va izohi (bitta katakli qatorlar)
            foreach (array_slice($rows, 0, $headerIndex) as $row) {
                $text = trim(implode(' ', array_filter(array_map('trim', $row))));

                if ($text === '') {
                    continue;
                }

                $title === null ? $title = $text : ($description ??= $text);
            }

            $map = self::mapFromHeader($rows[$headerIndex]);
            $rows = array_slice($rows, $headerIndex + 1);
        } elseif (self::looksNumbered($rows)) {
            // Sarlavhasiz, lekin 1-ustun tartib raqami bo'lsa — bir ustun surish
            $map = ['word' => 1, 'uz' => 2, 'en' => 3, 'ru' => 4, 'example' => 5, 'category' => 6];
        }

        $items = [];

        foreach ($rows as $row) {
            $word = self::cell($row, $map['word']);
            $uz = self::cell($row, $map['uz']);

            if ($word === '' || $uz === '') {
                continue;
            }

            $translations = array_filter([
                'uz' => $uz,
                'en' => self::cell($row, $map['en']),
                'ru' => self::cell($row, $map['ru']),
            ], fn ($v) => $v !== '');

            $items[] = [
                'word' => $word,
                'translations' => $translations,
                'example' => self::cell($row, $map['example']) ?: null,
                'category' => self::cell($row, $map['category']) ?: null,
            ];
        }

        return ['title' => $title, 'description' => $description, 'items' => $items];
    }

    /**
     * Sarlavha qatori: "so'z/word/parola/italy" va "tarjima/translation/o'zbek" ustunlari bo'lgan qator.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private static function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 15, true) as $index => $row) {
            $cells = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $row);
            $hasWord = collect($cells)->contains(fn ($h) => preg_match('/so.?z|word|parola|italy|italia|слов|итал/u', $h));
            $hasTranslation = collect($cells)->contains(fn ($h) => preg_match('/tarjima|translation|javob|uzbek|o.?zbek|перевод|узбек/u', $h));

            if ($hasWord && $hasTranslation) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, ?int>
     */
    private static function mapFromHeader(array $header): array
    {
        $map = ['word' => null, 'uz' => null, 'en' => null, 'ru' => null, 'example' => null, 'category' => null];

        foreach ($header as $i => $raw) {
            $h = mb_strtolower(trim((string) $raw));

            if ($h === '' || preg_match('/^(№|#|n|no|t\/r|id)\.?$/u', $h)) {
                continue;
            }

            match (true) {
                $map['en'] === null && (bool) preg_match('/^(en|eng|english|ingliz|англ)/u', $h) => $map['en'] = $i,
                $map['ru'] === null && (bool) preg_match('/^(ru|rus|russian|русск)/u', $h) => $map['ru'] = $i,
                $map['example'] === null && (bool) preg_match('/misol|example|esempio|пример/u', $h) => $map['example'] = $i,
                $map['category'] === null && (bool) preg_match('/kategor|category|mavzu|категор/u', $h) => $map['category'] = $i,
                $map['uz'] === null && (bool) preg_match('/tarjima|translation|javob|uzbek|o.?zbek|перевод|узбек/u', $h) => $map['uz'] = $i,
                $map['word'] === null && (bool) preg_match('/so.?z|word|parola|italy|italia|слов|итал/u', $h) => $map['word'] = $i,
                default => null,
            };
        }

        $map['word'] ??= 0;
        $map['uz'] ??= 1;

        return $map;
    }

    /** Birinchi ustun faqat tartib raqamlaridan iboratmi */
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
