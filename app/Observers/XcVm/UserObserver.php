<?php

namespace App\Observers\XcVm;

use App\Models\User;

class UserObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        return 'user';
    }

    protected function isClient(User $user): bool
    {
        return (string) $user->role === 'client'
            && ! (bool) $user->is_admin
            && ! (bool) $user->is_reseller;
    }

    public function created(User $user): void
    {
        if ($this->isClient($user)) {
            $this->queueUpsert($user);
        }
    }

    public function updated(User $user): void
    {
        if ($this->isClient($user)) {
            $this->queueUpsert($user);
        }
    }

    public function restored(User $user): void
    {
        if ($this->isClient($user)) {
            $this->queueUpsert($user);
        }
    }

    public function deleted(User $user): void
    {
        if ($this->isClient($user)) {
            $this->queueDelete($user);
        }
    }
}