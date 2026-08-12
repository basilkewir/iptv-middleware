<?php

namespace App\Repositories;

use App\Models\VOD;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VODRepository
{
    public function __construct(
        protected VOD $model
    ) {}

    public function getAllActive(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    public function getAllPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findById(int $id): ?VOD
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): ?VOD
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function getByCategory(int $categoryId): Collection
    {
        return $this->model->where('category_id', $categoryId)->where('is_active', true)->get();
    }

    public function getFreeContent(): Collection
    {
        return $this->model->where('is_free', true)->where('is_active', true)->get();
    }

    public function getFeatured(): Collection
    {
        return $this->model->where('is_featured', true)->where('is_active', true)->get();
    }

    public function getRecent(int $limit = 10): Collection
    {
        return $this->model->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function search(string $query): Collection
    {
        return $this->model->where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orWhere('actors', 'like', "%{$query}%")
            ->get();
    }

    public function create(array $data): VOD
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?VOD
    {
        $vod = $this->findById($id);

        if ($vod) {
            $vod->update($data);
        }

        return $vod;
    }

    public function delete(int $id): bool
    {
        $vod = $this->findById($id);

        return $vod ? $vod->delete() : false;
    }

    public function count(): int
    {
        return $this->model->count();
    }
}
