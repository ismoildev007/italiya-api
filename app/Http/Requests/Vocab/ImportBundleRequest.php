<?php

namespace App\Http\Requests\Vocab;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

/**
 * Bitta Excel/CSV (so'z | tarjima | kategoriya) → lug'at to'plami + so'z o'yini + mashq testi.
 * Fayl formati: `italyan_50_soz.xlsx` kabi — sarlavha qatorlari bo'lishi mumkin, `№` ustuni tashlanadi.
 */
class ImportBundleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,tsv,xlsx|max:10240',
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            // bo'sh qoldirilsa fayl sarlavhasi yoki fayl nomi olinadi
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'teacher_id' => 'nullable|exists:teachers,id',
            // test shu kunlik darsga biriktiriladi (unit.test_template_id bo'sh bo'lsa)
            'course_unit_id' => 'nullable|exists:course_units,id',
            'create_game' => 'boolean',
            'create_test' => 'boolean',
            'game_type' => ['nullable', Rule::in(array_keys(config('academy.games.types')))],
            // o'yindagi savollar soni (to'plamdagi so'zlardan ko'p bo'lmaydi)
            'card_count' => 'nullable|integer|min:3|max:100',
            // testdagi savollar soni (standart: so'zlar soni, ko'pi bilan 30)
            'question_count' => 'nullable|integer|min:2|max:100',
            'is_published' => 'boolean',
        ];
    }
}
