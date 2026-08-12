<?php

namespace App\Repositories;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionRepository
{
    public function __construct(
        protected Subscription $model
    ) {}

    public function getAllPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findById(int $id): ?Subscription
    {
        return $this->model->find($id);
    }

    public function getActiveByUserId(int $userId): ?Subscription
    {
        return $this->model->where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();
    }

    public function getExpiredSubscriptions(): Collection
    {
        return $this->model->where('status', 'active')
            ->where('end_date', '<', now())
            ->get();
    }

    public function getExpiringSoon(int $days = 3): Collection
    {
        return $this->model->where('status', 'active')
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addDays($days))
            ->get();
    }

    public function getByUserId(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): Subscription
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Subscription
    {
        $subscription = $this->findById($id);

        if ($subscription) {
            $subscription->update($data);
        }

        return $subscription;
    }

    public function cancel(int $id): ?Subscription
    {
        return $this->update($id, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function countActive(): int
    {
        return $this->model->where('status', 'active')
            ->where('end_date', '>', now())
            ->count();
    }
}
