<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XcVmMapping extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'xc_vm_id',
        'meta',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'xc_vm_id' => 'integer',
        'meta' => 'array',
    ];

    public static function lookup(string $entityType, int $entityId): ?int
    {
        return static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->value('xc_vm_id');
    }

    public static function findByRemote(string $entityType, int $xcVmId): ?self
    {
        return static::where('entity_type', $entityType)
            ->where('xc_vm_id', $xcVmId)
            ->first();
    }

    public static function remember(string $entityType, int $entityId, int $xcVmId, array $meta = []): self
    {
        return static::updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            ['xc_vm_id' => $xcVmId, 'meta' => $meta ?: null]
        );
    }

    public static function forget(string $entityType, int $entityId): void
    {
        static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }
}