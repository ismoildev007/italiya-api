<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/** Sozlamalar (mockup settings modal): bildirishnoma, ovoz, mavzu, tillar. */
class UpdateSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'notifications' => 'boolean',
            'sound' => 'boolean',
            'theme' => 'nullable|in:auto,light,dark',
            'ui_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            'preferred_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
        ];
    }
}
