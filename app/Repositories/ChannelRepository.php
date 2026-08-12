<?php

namespace App\Repositories;

use App\Models\Channel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ChannelRepository
{
    public function __construct(
        protected Channel $model
    ) {}

    public function getAllActive(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    public function getAllPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findById(int $id): ?Channel
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): ?Channel
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function getByCategory(int $categoryId): Collection
    {
        return $this->model->where('category_id', $categoryId)->where('is_active', true)->get();
    }

    public function getFreeChannels(): Collection
    {
        return $this->model->where('is_free', true)->where('is_active', true)->get();
    }

    public function search(string $query): Collection
    {
        return $this->model->where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get();
    }

    public function create(array $data): Channel
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Channel
    {
        $channel = $this->findById($id);

        if ($channel) {
            $channel->update($data);
        }

        return $channel;
    }

    public function delete(int $id): bool
    {
        $channel = $this->findById($id);

        return $channel ? $channel->delete() : false;
    }

    public function count(): int
    {
        return $this->model->count();
    }
}
