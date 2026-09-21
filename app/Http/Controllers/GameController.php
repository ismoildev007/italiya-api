<?php

namespace App\Http\Controllers;

use App\Http\Requests\Game\IndexGameRequest;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameSessionResource;
use App\Models\Game;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** So'zlashuv o'yinlari: har bir daraja uchun, savollar soni belgilanadi, liderbord. */
class GameController extends Controller
{
    public function __construct(private readonly GameService $games) {}

    public function index(IndexGameRequest $request): JsonResponse
    {
        return $this->ok(GameResource::collection($this->games->paginate($request->filters(), $request->perPage())));
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        return $this->created(new GameResource($this->games->save($request->validated())), 'O\'yin yaratildi.');
    }

    public function show(Game $game): JsonResponse
    {
        return $this->ok(new GameResource($game->load(['deck', 'teacher.user'])->loadCount('sessions')));
    }

    public function update(UpdateGameRequest $request, Game $game): JsonResponse
    {
        return $this->updated(new GameResource($this->games->save($request->validated(), $game)), 'O\'yin yangilandi.');
    }

    public function destroy(Game $game): JsonResponse
    {
        $this->games->delete($game);

        return $this->deleted('O\'yin o\'chirildi.');
    }

    public function results(Request $request, Game $game): JsonResponse
    {
        return $this->ok(GameSessionResource::collection($this->games->results($game, (int) $request->input('per_page', 20))));
    }

    public function leaderboard(Request $request, ?Game $game = null): JsonResponse
    {
        return $this->ok($this->games->leaderboard($game, $request->only(['level', 'class_id']), (int) $request->input('limit', 20)));
    }
}
