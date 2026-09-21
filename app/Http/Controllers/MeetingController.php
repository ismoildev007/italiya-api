<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\IndexMeetingRequest;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;

/** Zoom / Google Meet onlayn darslar. */
class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $meetings) {}

    public function index(IndexMeetingRequest $request): JsonResponse
    {
        return $this->ok(MeetingResource::collection($this->meetings->paginate($request->filters(), $request->perPage())));
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        return $this->created(new MeetingResource($this->meetings->save($request->validated())), 'Miting rejalashtirildi.');
    }

    public function show(Meeting $meeting): JsonResponse
    {
        return $this->ok(new MeetingResource($meeting->load(['teacher.user', 'groups'])));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        return $this->updated(new MeetingResource($this->meetings->save($request->validated(), $meeting)), 'Miting yangilandi.');
    }

    public function destroy(Meeting $meeting): JsonResponse
    {
        $this->meetings->delete($meeting);

        return $this->deleted('Miting o\'chirildi.');
    }
}
