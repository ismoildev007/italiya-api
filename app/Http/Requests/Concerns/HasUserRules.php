<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Foydalanuvchi akkaunti bilan bog'liq takrorlanuvchi qoidalar
 * (users, teachers, guardians so'rovlarida bir xil).
 */
trait HasUserRules
{
    protected const USERNAME_PATTERN = 'regex:/^[a-z0-9][a-z0-9._-]*$/i';

    protected function usernameRule(?int $ignoreId = null): array
    {
        return ['string', 'max:100', self::USERNAME_PATTERN, Rule::unique('users', 'username')->ignore($ignoreId)];
    }

    protected function phoneRule(?int $ignoreId = null): array
    {
        return ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($ignoreId)];
    }

    protected function emailRule(?int $ignoreId = null): array
    {
        return ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($ignoreId)];
    }

    /** Ism/familiya/otasining ismi. */
    protected function nameRules(string $presence = 'required'): array
    {
        return [
            'firstname' => "{$presence}|string|max:100",
            'lastname' => "{$presence}|string|max:100",
            'middlename' => 'nullable|string|max:100',
        ];
    }

    public function usernameMessages(): array
    {
        return [
            'username.regex' => 'Login faqat lotin harflari, raqamlar, nuqta, tire va pastki chiziqdan iborat bo\'lishi kerak (masalan: alisher.usmonov).',
        ];
    }
}
