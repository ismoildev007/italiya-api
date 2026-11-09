<?php

namespace App\Support;

/**
 * YouTube havolalari: video darsliklar YouTube'da joylashadi, frontend iframe orqali ko'rsatadi.
 */
final class YouTube
{
    /** watch?v=, youtu.be/, embed/, shorts/, live/ ko'rinishlaridan video id'sini ajratadi */
    public static function id(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{6,})~i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function isYouTube(?string $url): bool
    {
        return self::id($url) !== null;
    }

    public static function embedUrl(?string $url): ?string
    {
        $id = self::id($url);

        return $id ? "https://www.youtube.com/embed/{$id}?rel=0" : null;
    }
}
