<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:info,channel_update,subscription_expiry,maintenance,promotion,system',
            'recipients' => 'required|in:all,active,expiring,expired,resellers,clients,custom',
            'selectedUsers' => 'required_if:recipients,custom|array',
            'selectedUsers.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'channelId' => 'nullable|exists:channels,id',
            'warningDays' => 'nullable|integer|min:1|max:30',
            'priority' => 'required|in:low,normal,high,urgent',
            'schedule' => 'required|in:now,later',
            'scheduledAt' => 'required_if:schedule,later|nullable|date|after:now',
        ]);

        $query = User::query();

        switch ($validated['recipients']) {
            case 'active':
                $query->whereHas('subscriptions', fn($q) => $q->where('status', 'active'));
                break;
            case 'expiring':
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', 'active')
                      ->where('end_date', '<=', now()->addDays(3))
                      ->where('end_date', '>=', now());
                });
                break;
            case 'expired':
                $query->whereHas('subscriptions', fn($q) => $q->where('status', 'expired'));
                break;
            case 'resellers':
                $query->where('role', 'reseller');
                break;
            case 'clients':
                $query->where('role', 'client');
                break;
            case 'custom':
                $query->whereIn('id', $validated['selectedUsers']);
                break;
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return back()->withErrors(['recipients' => 'No recipients found for the selected criteria.']);
        }

        $data = [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'channel_id' => $validated['channelId'] ?? null,
            'warning_days' => $validated['warningDays'] ?? null,
            'sent_by' => auth()->id(),
        ];

        if ($validated['schedule'] === 'later') {
            $data['scheduled_at'] = $validated['scheduledAt'];
        }

        $count = 0;
        foreach ($recipients as $recipient) {
            Notification::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'type' => $validated['type'],
                'data' => $data,
                'read_at' => null,
            ]);
            $count++;
        }

        return back()->with('success', "Notification sent to {$count} user(s).");
    }
}
