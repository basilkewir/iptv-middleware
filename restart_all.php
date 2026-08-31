#!/usr/bin/env php
<?php
$dir = '/home/kotelhms/middleware';
chdir($dir);
define('LARAVEL_START', microtime(true));
require $dir.'/vendor/autoload.php';
$app = require_once $dir.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Services\AdminChannel\MyChannelHlsService;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Support\Str;

// 1. Restart multicast readers
echo "=== Multicast Readers ===\n";
$s = app(MulticastIngestService::class);
$ch = App\Models\Channel::where('stream_type', 'udp')->where('is_active', true)->first();
if ($ch) {
    echo "Using channel: {$ch->name} ({$ch->id})\n";
    $result = $s->ensureGroupReader($ch);
    echo "ensureGroupReader: ".($result ? "OK" : "FAIL")."\n";
    echo "isGroupRunning: ".($s->isGroupRunning($ch) ? "YES" : "NO")."\n";
} else {
    echo "No active UDP channel found!\n";
}

// 2. Restart Golden K admin channel playout
echo "\n=== Golden K Playout ===\n";
$gk = AdminChannel::find(1);
if (!$gk) {
    echo "Golden K not found!\n";
    exit(1);
}
echo "Channel: {$gk->channel_name}\n";

// Kill any existing playout
$svc = app(MyChannelHlsService::class);
$svc->stop($gk);
sleep(1);

// Create broadcast record and start
$broadcast = MyChannelBroadcast::create([
    'channel_id' => $gk->id,
    'session_id' => Str::uuid()->toString(),
    'start_time' => now(),
    'scheduled_end' => now()->addHours(24),
    'status' => 'starting',
    'playlist_snapshot' => $gk->myChannelPlaylist()->with('content')->get()->toJson(),
]);
$ok = $svc->start($broadcast);
echo "Golden K playout: ".($ok ? "OK" : "FAIL")."\n";

echo "\n=== Done ===\n";
