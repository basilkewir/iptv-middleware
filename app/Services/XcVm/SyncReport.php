<?php

namespace App\Services\XcVm;

use App\Services\XcVm\Exceptions\XcVmException;
use Illuminate\Support\Collection;

class SyncReport
{
    /** @var Collection<int, SyncResult> */
    public readonly Collection $results;

    public function __construct(Collection $results)
    {
        $this->results = $results;
        $this->results->each(fn (SyncResult $r) => $r);
    }

    public function append(SyncResult $result, ?callable $progress = null): void
    {
        $this->results->push($result);

        if ($progress !== null) {
            $progress($result);
        }
    }

    public function throws(): void
    {
        if (count($this->failures()) > 0) {
            $first = $this->failures()->first();
            throw new XcVmException("XC-VM sync failed: {$first->label} / {$first->error}");
        }
    }

    public function failures(): Collection
    {
        return $this->results->filter(fn (SyncResult $r) => $r->action === SyncResult::FAILED);
    }

    public function count(string $action): int
    {
        return $this->results->filter(fn (SyncResult $r) => $r->action === $action)->count();
    }

    public function total(): int
    {
        return $this->results->count();
    }

    public function wasSuccessful(): bool
    {
        return $this->failures()->isEmpty();
    }

    public function summary(): array
    {
        return [
            'total' => $this->total(),
            'created' => $this->count(SyncResult::CREATED),
            'updated' => $this->count(SyncResult::UPDATED),
            'deleted' => $this->count(SyncResult::DELETED),
            'skipped' => $this->count(SyncResult::SKIPPED),
            'failed' => $this->count(SyncResult::FAILED),
        ];
    }
}