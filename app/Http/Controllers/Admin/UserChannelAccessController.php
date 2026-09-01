<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminChannel\AdminChannel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserChannelAccessController extends Controller
{
    public function edit(Request $request, User $user): InertiaResponse
    {
        $channels = AdminChannel::where('is_my_channel', true)
            ->orderBy('channel_name')
            ->get(['id', 'channel_name']);
        $assigned = $user->managedChannels()->pluck('admin_channels.id');

        return Inertia::render('Admin/Users/Channels', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
            ],
            'channels' => $channels,
            'assigned_channel_ids' => $assigned,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'exists:admin_channels,id',
        ]);

        $user->managedChannels()->sync($validated['channel_ids'] ?? []);

        return redirect()->route('admin.users.channels', $user->id)
            ->with('success', 'Channel access updated successfully.');
    }
}