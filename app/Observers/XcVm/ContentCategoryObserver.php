<?php

namespace App\Observers\XcVm;

use App\Models\ContentCategory;

class ContentCategoryObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        return 'category';
    }

    public function created(ContentCategory $category): void
    {
        $this->queueUpsert($category);
    }

    public function updated(ContentCategory $category): void
    {
        $this->queueUpsert($category);
    }

    public function restored(ContentCategory $category): void
    {
        $this->queueUpsert($category);
    }

    public function deleted(ContentCategory $category): void
    {
        $this->queueDelete($category);
    }
}