<?php

namespace App\Services\XcVm;

class SyncResult
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $action,
        public readonly ?int $xcVmId = null,
        public readonly ?string $label = null,
        public readonly ?string $error = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->action !== self::FAILED && $this->action !== self::SKIPPED;
    }

    /**
     * Invoke a progress callback with this result and return the same result.
     */
    public function then(?callable $progress): self
    {
        if ($progress !== null) {
            $progress($this);
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'xc_vm_id' => $this->xcVmId,
            'label' => $this->label,
            'error' => $this->error,
        ];
    }
}