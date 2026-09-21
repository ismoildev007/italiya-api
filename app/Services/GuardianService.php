<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianService
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Guardian::query()
            ->with('students')
            ->search($filters['search'] ?? null, ['firstname', 'lastname', 'phone'])
            ->filter($filters, [
                'student_id' => ['students.id', 'has'],
                'linked' => fn ($q, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('telegram_chat_id')
                    : $q->whereNull('telegram_chat_id'),
            ])
            ->orderBy('lastname')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Guardian
    {
        return DB::transaction(function () use ($data) {
            $guardian = Guardian::create([
                ...array_intersect_key($data, array_flip([
                    'firstname', 'lastname', 'middlename', 'phone', 'workplace', 'passport_number',
                ])),
                'user_id' => ($data['create_account'] ?? false) ? $this->createAccount($data)->id : null,
                // Telegram botga bog'lash uchun bir martalik kod
                'link_code' => $this->newLinkCode(),
            ]);

            $this->attachStudent($guardian, $data);

            return $guardian->load('students');
        });
    }

    public function update(Guardian $guardian, array $data): Guardian
    {
        $guardian->update($data);

        return $guardian->fresh('students');
    }

    public function delete(Guardian $guardian): void
    {
        $guardian->delete();
    }

    public function withDetails(Guardian $guardian): Guardian
    {
        return $guardian->load(['students.schoolClass', 'user']);
    }

    /**
     * @param  array{student_id:int, relation:string, is_primary?:bool}  $data
     */
    public function attachStudent(Guardian $guardian, array $data): Guardian
    {
        $guardian->students()->syncWithoutDetaching([
            $data['student_id'] => [
                'school_id' => config('school.id'),
                'relation' => $data['relation'],
                'is_primary' => $data['is_primary'] ?? false,
            ],
        ]);

        return $guardian->fresh('students');
    }

    public function detachStudent(Guardian $guardian, Student $student): void
    {
        $guardian->students()->detach($student->id);
    }

    /**
     * Yangi bog'lash kodi — eski Telegram ulanishi bekor qilinadi.
     */
    public function regenerateLinkCode(Guardian $guardian): Guardian
    {
        $guardian->update([
            'link_code' => $this->newLinkCode(),
            'telegram_chat_id' => null,
            'linked_at' => null,
        ]);

        return $guardian->fresh();
    }

    private function createAccount(array $data): User
    {
        $user = User::create([
            ...array_intersect_key($data, array_flip(['firstname', 'lastname', 'middlename', 'phone'])),
            'username' => $data['username'] ?? 'ota'.Str::lower(Str::random(6)),
            'password' => $data['password'] ?? Str::random(10),
            'status' => 'active',
            'locale' => config('school.locale'),
        ]);

        $user->roles()->sync(['parent']);

        return $user;
    }

    private function newLinkCode(): string
    {
        return Str::upper(Str::random(8));
    }
}
