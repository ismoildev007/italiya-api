<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

/**
 * Barcha API kontrollerlari uchun asos.
 *
 * Kontrollerlar faqat so'rovni servisga uzatadi va natijani resurs orqali
 * qaytaradi — biznes-logika `app/Services` da, validatsiya FormRequest'larda.
 */
abstract class Controller
{
    use ApiResponse;
}
