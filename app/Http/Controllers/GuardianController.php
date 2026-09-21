<?php

namespace App\Http\Controllers;

use App\Http\Requests\Guardian\AttachStudentRequest;
use App\Http\Requests\Guardian\IndexGuardianRequest;
use App\Http\Requests\Guardian\StoreGuardianRequest;
use App\Http\Requests\Guardian\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianService;
use Illuminate\Http\JsonResponse;

class GuardianController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
    ) {}

    public function index(IndexGuardianRequest $request): JsonResponse
    {
        return $this->ok(GuardianResource::collection(
            $this->guardians->paginate($request->filters(), $request->perPage())
        ));
    }

    public function store(StoreGuardianRequest $request): JsonResponse
    {
        return $this->created(
            new GuardianResource($this->guardians->create($request->validated())),
            'Ota-ona qo\'shildi.'
        );
    }

    public function show(Guardian $guardian): JsonResponse
    {
        return $this->ok(new GuardianResource($this->guardians->withDetails($guardian)));
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian): JsonResponse
    {
        return $this->updated(
            new GuardianResource($this->guardians->update($guardian, $request->validated())),
            'Ma\'lumotlar yangilandi.'
        );
    }

    public function destroy(Guardian $guardian): JsonResponse
    {
        $this->guardians->delete($guardian);

        return $this->deleted('Ota-ona o\'chirildi.');
    }

    /** Farzandga bog'lash */
    public function attachStudent(AttachStudentRequest $request, Guardian $guardian): JsonResponse
    {
        return $this->updated(
            new GuardianResource($this->guardians->attachStudent($guardian, $request->validated())),
            'Farzand biriktirildi.'
        );
    }

    public function detachStudent(Guardian $guardian, Student $student): JsonResponse
    {
        $this->guardians->detachStudent($guardian, $student);

        return $this->deleted('Bog\'lanish o\'chirildi.');
    }

    /**
     * Telegram botga bog'lash uchun yangi kod.
     * Ota-ona kodni botga yuboradi → bot `telegram_chat_id` ni saqlaydi.
     */
    public function regenerateLinkCode(Guardian $guardian): JsonResponse
    {
        $guardian = $this->guardians->regenerateLinkCode($guardian);

        return $this->ok([
            'link_code' => $guardian->link_code,
            'instructions' => 'Ushbu kodni Telegram botga yuboring — akkaunt farzandingizga bog\'lanadi.',
        ], 'Yangi bog\'lash kodi yaratildi.');
    }
}
