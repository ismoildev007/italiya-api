<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tariff\IndexTariffRequest;
use App\Http\Requests\Tariff\StoreTariffRequest;
use App\Http\Requests\Tariff\UpdateTariffRequest;
use App\Http\Resources\TariffResource;
use App\Models\Tariff;
use App\Services\TariffService;
use Illuminate\Http\JsonResponse;

class TariffController extends Controller
{
    public function __construct(
        private readonly TariffService $tariffs,
    ) {}

    public function index(IndexTariffRequest $request): JsonResponse
    {
        return $this->ok(TariffResource::collection($this->tariffs->list($request->filters())));
    }

    public function store(StoreTariffRequest $request): JsonResponse
    {
        return $this->created(new TariffResource($this->tariffs->create($request->validated())), 'Tarif yaratildi.');
    }

    public function show(Tariff $tariff): JsonResponse
    {
        return $this->ok(new TariffResource($tariff->loadCount('studentTariffs')));
    }

    public function update(UpdateTariffRequest $request, Tariff $tariff): JsonResponse
    {
        return $this->updated(
            new TariffResource($this->tariffs->update($tariff, $request->validated())),
            'Tarif yangilandi.'
        );
    }

    public function destroy(Tariff $tariff): JsonResponse
    {
        $this->tariffs->delete($tariff);

        return $this->deleted('Tarif o\'chirildi.');
    }
}
