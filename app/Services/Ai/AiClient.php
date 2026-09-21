<?php

namespace App\Services\Ai;

/**
 * Matn generatsiya qiluvchi AI provayderlar uchun umumiy interfeys.
 * Gemini / OpenAI / Anthropic — barchasi bir xil ishlatiladi.
 */
interface AiClient
{
    public function name(): string;

    public function model(): string;

    public function isConfigured(): bool;

    /** Oddiy matnli javob. */
    public function complete(string $prompt, ?string $system = null, array $options = []): string;

    /**
     * JSON obyekt qaytaradi (model matn ichiga JSON yozsa ham ajratib oladi).
     *
     * @return array<string, mixed>
     */
    public function json(string $prompt, ?string $system = null, array $options = []): array;

    /**
     * Audio faylni ham promptga qo'shib tahlil qiladi (speaking uchun).
     * Provayder audio qo'llamasa — faqat matn bilan ishlaydi.
     *
     * @return array<string, mixed>
     */
    public function jsonWithAudio(string $prompt, string $audioPath, ?string $system = null): array;
}
