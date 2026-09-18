<?php

namespace App\Services\XcVm\Syncers;

use App\Models\User;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class LineSyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        return 'user';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $user = $entity instanceof User ? $entity : User::find((int) $entity);
        if (! $user) {
            return $this->skipped((int) $entity, 'user not found');
        }

        $label = (string) $user->username;

        try {
            $payload = [
                'username' => $user->username,
                'password' => $this->linePassword($user),
                'exp_date' => $this->expiryDate($user),
                'max_connections' => max(1, (int) ($user->max_connections ?? 1)),
                'enabled' => $user->is_active ? 1 : 0,
                'is_mag' => 0,
                'is_e2' => 0,
                'is_stalker' => 0,
                'is_isplock' => 0,
                'admin_notes' => mb_substr('IPTV Middleware client #' . $user->id, 0, 255),
            ];

            $bouquetIds = $this->remoteBouquetIds($user);
            if ($bouquetIds !== []) {
                $payload['bouquets_selected'] = $bouquetIds;
            }

            $remoteId = $this->remoteId((int) $user->id);

            if ($remoteId !== null) {
                $this->client->editLine($remoteId, $payload);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createLine($payload);

                if (! $remoteId) {
                    return $this->failure((int) $user->id, 'XC-VM returned no line id', $label);
                }

                $action = SyncResult::CREATED;
            }

            // Status toggle after the fact: edit_line has no `enabled` field on
            // XC-VM, so use the dedicated actions.
            if ($user->is_active) {
                $this->client->enableLine($remoteId);
            } else {
                $this->client->disableLine($remoteId);
            }

            $this->remember((int) $user->id, $remoteId, [
                'username' => $user->username,
            ]);

            return new SyncResult($this->entityType(), (int) $user->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $user->id, $e->getMessage(), $label);
        }
    }

    public function delete(int $entityId): SyncResult
    {
        $mapping = XcVmMapping::where('entity_type', $this->entityType())
            ->where('entity_id', $entityId)
            ->first();

        if (! $mapping) {
            return $this->skipped($entityId, 'no remote mapping');
        }

        try {
            $this->client->deleteLine((int) $mapping->xc_vm_id);
            $this->forget($entityId);

            return $this->deleted($entityId);
        } catch (Throwable $e) {
            return $this->failure($entityId, $e->getMessage());
        }
    }

    public function queue(): Collection
    {
        return User::query()
            ->where('role', 'client')
            ->where('is_admin', false)
            ->where('is_reseller', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = $this->runQueue($progress, config('xcvm.prune_remote', false));

        return $this->report($results);
    }

    private function linePassword(User $user): string
    {
        if ($user->m3u_token && strlen((string) $user->m3u_token) >= 6) {
            return $user->m3u_token;
        }

        $password = Str::random((int) config('xcvm.line_password_length', 16));
        $user->forceFill(['m3u_token' => $password])->save();

        return $password;
    }

    private function expiryDate(User $user): ?string
    {
        $subscription = $user->activeSubscription();

        return $subscription?->end_date?->format('Y-m-d');
    }

    private function remoteBouquetIds(User $user): array
    {
        return $user->bouquets()
            ->where('is_active', true)
            ->pluck('bouquets.id')
            ->map(fn ($id) => XcVmMapping::lookup('bouquet', (int) $id))
            ->filter()
            ->values()
            ->all();
    }
}