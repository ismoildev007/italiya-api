<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tizim qo'llanmasi — foydalanuvchining rol/ruxsatlariga qarab filtrlanadi.
 * O'qituvchi faqat o'ziga ochiq bo'limlarni, o'quvchi kabinet qo'llanmasini ko'radi.
 */
class GuideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStudent = $user->hasRole('student');

        $sections = collect(config('guide.sections'))->filter(function (array $section) use ($user, $isStudent) {
            if (isset($section['role'])) {
                return $user->hasRole($section['role']) || $user->isSuperadmin();
            }

            if ($isStudent && ! $user->isSuperadmin()) {
                return false;
            }

            if (empty($section['permission'])) {
                return true;
            }

            return collect((array) $section['permission'])->contains(fn ($p) => $user->hasPermission($p));
        })->values();

        $groupKeys = $sections->pluck('group')->unique();

        return $this->ok([
            'groups' => collect(config('guide.groups'))->filter(fn ($g) => $groupKeys->contains($g['key']))->values(),
            'sections' => $sections,
            'features' => config('academy.features'),
        ]);
    }
}
