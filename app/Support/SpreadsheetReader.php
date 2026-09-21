<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * CSV / TSV / XLSX fayllarni qatorlar massiviga o'qiydi (tashqi kutubxonasiz).
 * Lug'at importi uchun: 1-ustun — so'z, 2-ustun — tarjima, 3 — misol, 4 — kategoriya.
 */
final class SpreadsheetReader
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function read(string $path, ?string $extension = null): array
    {
        $ext = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx' => self::readXlsx($path),
            'tsv' => self::readDelimited($path, "\t"),
            default => self::readDelimited($path, self::detectDelimiter($path)),
        };
    }

    private static function readDelimited(string $path, string $delimiter): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('Faylni o\'qib bo\'lmadi.');
        }

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $row = array_map(fn ($v) => trim((string) $v), $row);

            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        // UTF-8 BOM
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return $rows;
    }

    private static function detectDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r') ?: throw new RuntimeException('Faylni o\'qib bo\'lmadi.'));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('XLSX faylni ochib bo\'lmadi.');
        }

        $shared = [];

        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);

            foreach ($doc->si ?? [] as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('XLSX ichida varaq topilmadi.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::columnIndex(preg_replace('/\d+/', '', $ref) ?? 'A');
                $type = (string) $c['t'];
                $value = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : '');

                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }

                $cells[$col] = trim($value);
            }

            if ($cells === []) {
                continue;
            }

            $max = max(array_keys($cells));
            $line = [];

            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }

            if (implode('', $line) !== '') {
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }
}
