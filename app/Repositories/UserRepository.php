<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function __construct(
        protected User $model
    ) {}

    public function getAllPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function getSubscribers(): Collection
    {
        return $this->model->where('role', 'client')
            ->where('is_active', true)
            ->get();
    }

    public function getActiveUsers(int $days = 30): Collection
    {
        return $this->model->where('is_active', true)
            ->where('updated_at', '>=', now()->subDays($days))
            ->get();
    }

    public function search(string $query): Collection
    {
        return $this->model->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->get();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?User
    {
        $user = $this->findById($id);

        if ($user) {
            $user->update($data);
        }

        return $user;
    }

    public function delete(int $id): bool
    {
        $user = $this->findById($id);

        return $user ? $user->delete() : false;
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function countSubscribers(): int
    {
        return $this->model->where('role', 'client')->where('is_active', true)->count();
    }
}
