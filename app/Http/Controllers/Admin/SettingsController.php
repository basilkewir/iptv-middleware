<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    protected function updateSettings(Request $request, string $group): RedirectResponse
    {
        $settings = $request->input('settings');
        if (!is_array($settings)) {
            $settings = $request->except(['_token', '_method']);
        }
        foreach ($settings as $key => $value) {
            SystemSetting::set($key, is_string($value) ? $value : json_encode($value), $group);
        }
        return back()->with('success', ucfirst($group) . ' settings updated successfully.');
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'general' => SystemSetting::getGroup('general'),
                'localization' => SystemSetting::getGroup('localization'),
                'channels' => SystemSetting::getGroup('channels'),
                'vod' => SystemSetting::getGroup('vod'),
                'epg' => SystemSetting::getGroup('epg'),
                'users' => SystemSetting::getGroup('users'),
                'security' => SystemSetting::getGroup('security'),
                'roles' => SystemSetting::getGroup('roles'),
                'payments' => SystemSetting::getGroup('payments'),
                'billing' => SystemSetting::getGroup('billing'),
                'server' => SystemSetting::getGroup('server'),
                'cache' => SystemSetting::getGroup('cache'),
                'performance' => SystemSetting::getGroup('performance'),
                'email' => SystemSetting::getGroup('email'),
                'notifications' => SystemSetting::getGroup('notifications'),
                'logging' => SystemSetting::getGroup('logging'),
                'monitoring' => SystemSetting::getGroup('monitoring'),
                'backup' => SystemSetting::getGroup('backup'),
                'api' => SystemSetting::getGroup('api'),
                'integrations' => SystemSetting::getGroup('integrations'),
                'variables' => SystemSetting::getGroup('variables'),
                'cronjobs' => SystemSetting::getGroup('cronjobs'),
                'domains' => SystemSetting::getGroup('domains'),
            ],
        ]);
    }

    public function show(string $section): Response
    {
        $sections = [
            'general', 'localization', 'channels', 'vod', 'epg', 'users',
            'security', 'roles', 'payments', 'billing', 'server', 'cache',
            'performance', 'email', 'notifications', 'logging', 'monitoring',
            'backup', 'api', 'integrations', 'variables', 'cronjobs', 'domains',
        ];

        if (!in_array($section, $sections)) {
            abort(404);
        }

        return $this->{$section}();
    }

    public function general(): Response
    {
        return Inertia::render('Admin/Settings/General', [
            'settings' => SystemSetting::getGroup('general'),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'general');
    }

    public function localization(): Response
    {
        return Inertia::render('Admin/Settings/Localization', [
            'settings' => SystemSetting::getGroup('localization'),
        ]);
    }

    public function updateLocalization(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'localization');
    }

    public function channels(): Response
    {
        return Inertia::render('Admin/Settings/Channels', [
            'settings' => SystemSetting::getGroup('channels'),
        ]);
    }

    public function updateChannels(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'channels');
    }

    public function vod(): Response
    {
        return Inertia::render('Admin/Settings/Vod', [
            'settings' => SystemSetting::getGroup('vod'),
        ]);
    }

    public function updateVod(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'vod');
    }

    public function epg(): Response
    {
        return Inertia::render('Admin/Settings/Epg', [
            'settings' => SystemSetting::getGroup('epg'),
        ]);
    }

    public function updateEpg(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'epg');
    }

    public function users(): Response
    {
        return Inertia::render('Admin/Settings/UserManagement', [
            'settings' => SystemSetting::getGroup('users'),
        ]);
    }

    public function updateUsers(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'users');
    }

    public function security(): Response
    {
        return Inertia::render('Admin/Settings/Security', [
            'settings' => SystemSetting::getGroup('security'),
        ]);
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'security');
    }

    public function roles(): Response
    {
        return Inertia::render('Admin/Settings/Roles', [
            'settings' => SystemSetting::getGroup('roles'),
        ]);
    }

    public function updateRoles(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'roles');
    }

    public function payments(): Response
    {
        return Inertia::render('Admin/Settings/Payments', [
            'settings' => SystemSetting::getGroup('payments'),
        ]);
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'payments');
    }

    public function billing(): Response
    {
        return Inertia::render('Admin/Settings/Billing', [
            'settings' => SystemSetting::getGroup('billing'),
        ]);
    }

    public function updateBilling(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'billing');
    }

    public function server(): Response
    {
        return Inertia::render('Admin/Settings/ServerConfig', [
            'settings' => SystemSetting::getGroup('server'),
        ]);
    }

    public function updateServer(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'server');
    }

    public function cache(): Response
    {
        return Inertia::render('Admin/Settings/CacheSettings', [
            'settings' => SystemSetting::getGroup('cache'),
        ]);
    }

    public function updateCache(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'cache');
    }

    public function performance(): Response
    {
        return Inertia::render('Admin/Settings/Performance', [
            'settings' => SystemSetting::getGroup('performance'),
        ]);
    }

    public function updatePerformance(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'performance');
    }

    public function email(): Response
    {
        return Inertia::render('Admin/Settings/EmailSettings', [
            'settings' => SystemSetting::getGroup('email'),
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'email');
    }

    public function notifications(): Response
    {
        return Inertia::render('Admin/Settings/NotificationSettings', [
            'settings' => SystemSetting::getGroup('notifications'),
        ]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'notifications');
    }

    public function logging(): Response
    {
        return Inertia::render('Admin/Settings/Logging', [
            'settings' => SystemSetting::getGroup('logging'),
        ]);
    }

    public function updateLogging(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'logging');
    }

    public function monitoring(): Response
    {
        return Inertia::render('Admin/Settings/Monitoring', [
            'settings' => SystemSetting::getGroup('monitoring'),
        ]);
    }

    public function updateMonitoring(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'monitoring');
    }

    public function backup(): Response
    {
        return Inertia::render('Admin/Settings/Backup', [
            'settings' => SystemSetting::getGroup('backup'),
        ]);
    }

    public function updateBackup(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'backup');
    }

    public function api(): Response
    {
        return Inertia::render('Admin/Settings/ApiSettings', [
            'settings' => SystemSetting::getGroup('api'),
        ]);
    }

    public function updateApi(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'api');
    }

    public function integrations(): Response
    {
        return Inertia::render('Admin/Settings/Integrations', [
            'settings' => SystemSetting::getGroup('integrations'),
        ]);
    }

    public function updateIntegrations(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'integrations');
    }

    public function variables(): Response
    {
        return Inertia::render('Admin/Settings/Variables', [
            'settings' => SystemSetting::getGroup('variables'),
        ]);
    }

    public function updateVariables(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'variables');
    }

    public function cronjobs(): Response
    {
        return Inertia::render('Admin/Settings/Cronjobs', [
            'settings' => SystemSetting::getGroup('cronjobs'),
        ]);
    }

    public function updateCronjobs(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'cronjobs');
    }

    public function domains(): Response
    {
        return Inertia::render('Admin/Settings/Domains', [
            'settings' => SystemSetting::getGroup('domains'),
        ]);
    }

    public function updateDomains(Request $request): RedirectResponse
    {
        return $this->updateSettings($request, 'domains');
    }

    public function regenerateApiKeys(Request $request): RedirectResponse
    {
        $users = \App\Models\User::where('is_active', true)->get();
        foreach ($users as $user) {
            $user->api_key = \Illuminate\Support\Str::random(40);
            $user->save();
        }
        return back()->with('success', 'All API keys have been regenerated.');
    }

    public function runBackup(Request $request): RedirectResponse
    {
        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql';
        $path = storage_path('app/backups');
        if (!is_dir($path)) mkdir($path, 0755, true);

        $db = config('database.connections.mysql');
        $cmd = sprintf('mysqldump -h %s -u %s %s > %s 2>&1',
            escapeshellarg($db['host']),
            escapeshellarg($db['username']),
            escapeshellarg($db['database']),
            escapeshellarg($path . '/' . $filename)
        );
        if (isset($db['password']) && $db['password']) {
            $cmd = sprintf('mysqldump -h %s -u %s -p%s %s > %s 2>&1',
                escapeshellarg($db['host']),
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['database']),
                escapeshellarg($path . '/' . $filename)
            );
        }
        exec($cmd, $output, $returnVar);
        if ($returnVar !== 0) {
            return back()->with('error', 'Backup failed: ' . implode("\n", $output));
        }
        return back()->with('success', "Backup created: {$filename}");
    }

    public function restoreBackup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string',
        ]);
        $path = storage_path('app/backups/' . $validated['filename']);
        if (!file_exists($path)) {
            return back()->with('error', 'Backup file not found.');
        }
        $db = config('database.connections.mysql');
        $cmd = sprintf('mysql -h %s -u %s %s < %s 2>&1',
            escapeshellarg($db['host']),
            escapeshellarg($db['username']),
            escapeshellarg($db['database']),
            escapeshellarg($path)
        );
        if (isset($db['password']) && $db['password']) {
            $cmd = sprintf('mysql -h %s -u %s -p%s %s < %s 2>&1',
                escapeshellarg($db['host']),
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['database']),
                escapeshellarg($path)
            );
        }
        exec($cmd, $output, $returnVar);
        if ($returnVar !== 0) {
            return back()->with('error', 'Restore failed: ' . implode("\n", $output));
        }
        return back()->with('success', 'Database restored successfully.');
    }

    public function clearCache(Request $request): RedirectResponse
    {
        \Illuminate\Support\Facades\Cache::flush();
        return back()->with('success', 'Cache cleared successfully.');
    }

    public function runCronjob(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_name' => 'required|string',
        ]);
        $task = $validated['task_name'];
        switch ($task) {
            case 'cleanup_expired':
                \App\Models\Subscription::where('status', 'active')->where('end_date', '<', now())->update(['status' => 'expired']);
                break;
            case 'process_epg':
                \App\Jobs\ProcessEPG::dispatch();
                break;
            case 'send_reminders':
                $expiring = \App\Models\Subscription::with('user')->where('status', 'active')->where('end_date', '<=', now()->addDays(3))->where('end_date', '>=', now())->get();
                foreach ($expiring as $sub) {
                    \App\Jobs\SendSubscriptionReminder::dispatch($sub);
                }
                break;
            case 'health_check':
                \App\Jobs\StreamHealthCheck::dispatch();
                break;
            default:
                return back()->with('error', "Unknown task: {$task}");
        }
        return back()->with('success', "Cron job \"{$task}\" triggered successfully.");
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test email from IPTV Middleware.\n\nSent at: " . now()->toDateTimeString(),
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('IPTV Middleware - Test Email');
                }
            );
            return back()->with('success', "Test email sent to {$validated['email']}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    public function viewLogs(Request $request): Response
    {
        $logs = [];
        $logPath = storage_path('logs');
        if (is_dir($logPath)) {
            $files = collect(scandir($logPath))->filter(fn ($f) => $f !== '.' && $f !== '..' && str_ends_with($f, '.log'));
            $latest = $files->sort()->last();
            if ($latest) {
                $lines = file(storage_path("logs/{$latest}"));
                $logs = array_slice($lines, -200);
            }
        }
        return Inertia::render('Admin/Settings/Logging', [
            'settings' => SystemSetting::getGroup('logging'),
            'logContent' => implode('', $logs),
        ]);
    }

    public function clearLogs(Request $request): RedirectResponse
    {
        $logPath = storage_path('logs');
        if (is_dir($logPath)) {
            $files = collect(scandir($logPath))->filter(fn ($f) => str_ends_with($f, '.log'));
            foreach ($files as $file) {
                \Illuminate\Support\Facades\File::put($logPath . '/' . $file, '');
            }
        }
        return back()->with('success', 'All logs have been cleared.');
    }
}
