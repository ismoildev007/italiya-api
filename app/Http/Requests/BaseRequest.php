<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    /** Sahifalash parametrlari — filtrlarga qo'shilmaydi. */
    protected const PAGINATION_KEYS = ['per_page', 'page', 'sort', 'direction'];

    public function authorize(): bool
    {
        // Ruxsat `permission` middleware darajasida tekshiriladi
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validatsiya xatosi',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Servisga uzatiladigan filtrlar — sahifalash kalitlarisiz.
     *
     * @param  array<int, string>  $except
     */
    public function filters(array $except = []): array
    {
        return $this->payload([...self::PAGINATION_KEYS, ...$except]);
    }

    /**
     * Tekshirilgan ma'lumotlar, ko'rsatilgan kalitlarsiz.
     *
     * @param  array<int, string>  $except
     */
    public function payload(array $except = []): array
    {
        return array_diff_key($this->validated(), array_flip($except));
    }

    /**
     * Har bir ro'yxat so'rovida takrorlanadigan sahifalash qoidalari.
     */
    protected function paginationRules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function perPage(int $default = 20): int
    {
        return max(1, min(200, (int) $this->input('per_page', $default)));
    }
}
