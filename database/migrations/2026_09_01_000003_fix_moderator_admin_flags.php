<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Moderators were previously given is_admin=true by updateFlagsFromRoles(),
 * which unlocked full channel visibility. Recompute flags so restricted roles
 * are not treated as admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = User::where('is_admin', true)
            ->whereHas('roles', function ($q) {
                $q->whereNotIn('name', ['super_admin', 'admin', 'reseller']);
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['super_admin', 'admin']);
            })
            ->get();

        foreach ($users as $user) {
            $user->updateFlagsFromRoles();
        }
    }

    public function down(): void
    {
        // Intentionally not reversible.
    }
};