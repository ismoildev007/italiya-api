<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use Illuminate\Http\JsonResponse;

/** AI provayderlar holati (admin "Sozlamalar"). */
class AiController extends Controller
{
    public function status(AiManager $ai): JsonResponse
    {
        return $this->ok($ai->status());
    }
}
