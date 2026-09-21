<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | AI provayderlar. Vazifalar bo'yicha taqsimot (config('services.ai')):
    |   generator  — test/darslik/lug'at generatsiyasi (gemini | openai | anthropic)
    |   evaluator  — writing/speaking javoblarini tahlil qilish (gemini)
    |   stt        — ovozni matnga o'girish (openai)
    |   tts        — matnni ovozga o'girish, listening audio (openai)
    */
    'ai' => [
        'generator' => env('AI_GENERATOR', 'gemini'),
        'evaluator' => env('AI_EVALUATOR', 'gemini'),
        'stt' => env('AI_STT', 'openai'),
        'tts' => env('AI_TTS', 'openai'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        // Bepul OpenAI-mos provayderlar (Groq, OpenRouter…) uchun manzil almashtiriladi
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'stt_model' => env('OPENAI_STT_MODEL', 'gpt-4o-mini-transcribe'),
        'tts_model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice' => env('OPENAI_TTS_VOICE', 'alloy'),
    ],

    'telegram' => [
        // Ota-onalar boti: qarzdorlik, oylik hisobot, baho/davomat xabarlari
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'enabled' => (bool) env('TELEGRAM_BOT_ENABLED', false),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        // O'quvchilar boti: faqat variantli testlarni yechish
        'student_token' => env('TELEGRAM_STUDENT_BOT_TOKEN'),
        'student_webhook_secret' => env('TELEGRAM_STUDENT_WEBHOOK_SECRET'),
    ],

];
