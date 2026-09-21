<?php

namespace App\Services\Ai\Concerns;

use RuntimeException;

trait ParsesJson
{
    /**
     * Model javobidan birinchi JSON obyektni ajratib oladi.
     *
     * @return array<string, mixed>
     */
    protected function parseJson(string $text): array
    {
        $text = trim($text);

        // ```json ... ``` bloklarini tozalaymiz
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;

        $decoded = json_decode($text, true);

        if (! is_array($decoded) && preg_match('/[\{\[].*[\}\]]/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('AI javobini JSON sifatida o\'qib bo\'lmadi.');
        }

        return $decoded;
    }

    protected function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a', 'mp4' => 'audio/mp4',
            'webm' => 'audio/webm',
            'ogg', 'oga' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }
}
