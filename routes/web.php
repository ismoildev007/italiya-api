<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'school' => config('school.name'),
    'api' => url('/api/v1'),
]));
