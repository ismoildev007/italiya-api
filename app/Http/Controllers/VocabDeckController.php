<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vocab\GenerateCardsRequest;
use App\Http\Requests\Vocab\ImportBundleRequest;
use App\Http\Requests\Vocab\ImportDeckRequest;
use App\Http\Requests\Vocab\IndexDeckRequest;
use App\Http\Requests\Vocab\StoreDeckRequest;
use App\Http\Requests\Vocab\UpdateDeckRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\TestTemplateResource;
use App\Http\Resources\VocabDeckResource;
use App\Models\VocabDeck;
use App\Services\VocabImportService;
use App\Services\VocabService;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;

/** Ovozli lug'at: to'plamlar, kartochkalar, AI generatsiya, Excel import, TTS. */
class VocabDeckController extends Controller
{
    public function __construct(private readonly VocabService $vocab) {}

    public function index(IndexDeckRequest $request): JsonResponse
    {
        return $this->ok(VocabDeckResource::collection($this->vocab->paginate($request->filters(), $request->perPage())));
    }

    /** AI orqali so'zlar ro'yxati (bazaga yozilmaydi) */
    public function generate(GenerateCardsRequest $request): JsonResponse
    {
        abort_unless(Feature::ai(), 422, 'AI funksiyasi o\'chirilgan. Yoqish uchun .env da AI_ENABLED=true.');

        return $this->ok(['cards' => $this->vocab->generateCards($request->validated())], 'So\'zlar generatsiya qilindi.');
    }

    public function store(StoreDeckRequest $request): JsonResponse
    {
        return $this->created(new VocabDeckResource($this->vocab->save($request->validated())), 'To\'plam saqlandi.');
    }

    public function show(VocabDeck $deck): JsonResponse
    {
        return $this->ok(new VocabDeckResource($deck->load(['cards', 'teacher.user'])));
    }

    public function update(UpdateDeckRequest $request, VocabDeck $deck): JsonResponse
    {
        return $this->updated(new VocabDeckResource($this->vocab->save($request->validated(), $deck)), 'To\'plam yangilandi.');
    }

    public function destroy(VocabDeck $deck): JsonResponse
    {
        $this->vocab->delete($deck);

        return $this->deleted('To\'plam o\'chirildi.');
    }

    /** Excel / CSV import (so'z | tarjima | en | ru | misol | kategoriya) */
    public function import(ImportDeckRequest $request, VocabDeck $deck): JsonResponse
    {
        $added = $this->vocab->import($deck, $request->file('file'));

        return $this->ok(new VocabDeckResource($deck->fresh(['cards'])), "{$added} ta so'z qo'shildi.");
    }

    /**
     * Bitta Excel/CSV → to'plam + so'z o'yini + mashq testi.
     * Format: [sarlavha] → № | Italiya so'zi | O'zbekcha tarjimasi | Kategoriya.
     */
    public function importBundle(ImportBundleRequest $request, VocabImportService $import): JsonResponse
    {
        $result = $import->importBundle($request->file('file'), $request->validated());

        return $this->created([
            'deck' => new VocabDeckResource($result['deck']),
            'game' => $result['game'] ? new GameResource($result['game']) : null,
            'test' => $result['test'] ? new TestTemplateResource($result['test']) : null,
            'imported' => $result['imported'],
        ], "{$result['imported']} ta so'z yuklandi".($result['game'] ? ', o\'yin' : '').($result['test'] ? ', test' : '').' yaratildi.');
    }

    /** Kartochkalar uchun talaffuz audiosi (OpenAI TTS) */
    public function generateAudio(VocabDeck $deck): JsonResponse
    {
        abort_unless(Feature::audio(), 422, 'Audio (tinglash) funksiyasi o\'chirilgan.');

        $count = $this->vocab->generateAudio($deck, request()->boolean('force'));

        return $this->ok(new VocabDeckResource($deck->fresh(['cards'])), "{$count} ta audio yaratildi.");
    }
}
