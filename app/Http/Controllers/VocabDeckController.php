<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vocab\GenerateCardsRequest;
use App\Http\Requests\Vocab\ImportDeckRequest;
use App\Http\Requests\Vocab\IndexDeckRequest;
use App\Http\Requests\Vocab\StoreDeckRequest;
use App\Http\Requests\Vocab\UpdateDeckRequest;
use App\Http\Resources\VocabDeckResource;
use App\Models\VocabDeck;
use App\Services\VocabService;
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

    /** Kartochkalar uchun talaffuz audiosi (OpenAI TTS) */
    public function generateAudio(VocabDeck $deck): JsonResponse
    {
        $count = $this->vocab->generateAudio($deck, request()->boolean('force'));

        return $this->ok(new VocabDeckResource($deck->fresh(['cards'])), "{$count} ta audio yaratildi.");
    }
}
