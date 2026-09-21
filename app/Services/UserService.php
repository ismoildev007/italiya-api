<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'middlename' => $data['middlename'] ?? null,
                'username' => $data['username'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? config('school.locale'),
                'status' => $data['status'] ?? 'active',
            ]);

            $this->syncRoles($user, $data['roles'] ?? []);

            return $user->load('roles');
        });
    }

    public function update(User $user, array $data): User
    {
        $this->guardSuperadmin($user, allowSelfPassword: true);

        return DB::transaction(function () use ($user, $data) {
            $payload = array_filter([
                'firstname' => $data['firstname'] ?? null,
                'lastname' => $data['lastname'] ?? null,
                'middlename' => $data['middlename'] ?? null,
                'username' => $data['username'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'locale' => $data['locale'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($v) => $v !== null);

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user = $this->users->update($user, $payload);

            if (array_key_exists('roles', $data)) {
                $this->syncRoles($user, $data['roles'] ?? []);
            }

            return $user->load('roles');
        });
    }

    public function delete(User $user): void
    {
        $this->guardSuperadmin($user);

        if ($user->id === auth()->id()) {
            abort(422, 'O\'zingizni o\'chira olmaysiz.');
        }

        $this->users->delete($user);
    }

    public function setStatus(User $user, string $status): User
    {
        $this->guardSuperadmin($user);

        if ($user->id === auth()->id()) {
            abort(422, 'O\'z akkauntingiz holatini o\'zgartira olmaysiz.');
        }

        return $this->users->update($user, ['status' => $status]);
    }

    public function resetPassword(User $user, string $password): User
    {
        return $this->users->update($user, ['password' => $password]);
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    public function syncRoles(User $user, array $roleNames): void
    {
        // Superadmin rolini faqat superadmin bera oladi
        if (in_array(config('authorization.super_role'), $roleNames, true) && ! auth()->user()?->isSuperadmin()) {
            abort(403, 'Superadmin rolini biriktirish uchun ruxsat yo\'q.');
        }

        $valid = Role::whereIn('name', $roleNames)->pluck('name')->all();

        $user->roles()->sync($valid);
        $user->unsetRelation('roles');
    }

    /** Superadmin akkauntini boshqalar o'zgartira/o'chira olmaydi. */
    private function guardSuperadmin(User $user, bool $allowSelfPassword = false): void
    {
        if (! $user->isSuperadmin()) {
            return;
        }

        if ($allowSelfPassword && auth()->id() === $user->id) {
            return;
        }

        if (auth()->user()?->isSuperadmin() && auth()->id() === $user->id) {
            return;
        }

        abort(403, 'Superadmin akkaunti himoyalangan.');
    }
}
