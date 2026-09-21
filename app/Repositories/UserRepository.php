<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->search($filters['search'] ?? null, ['firstname', 'lastname', 'middlename', 'username', 'phone'])
            ->filter($filters, [
                'role' => ['roles.name', 'has'],
                'status' => 'status',
                'permission' => ['roles.permissions.name', 'has'],
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function findByLogin(string $login): ?User
    {
        return User::where('username', $login)->orWhere('phone', $login)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
