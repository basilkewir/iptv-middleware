<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class RefreshYouTubeUrl extends Command
{
    protected $signature = 'youtube:refresh-url {channelId}';
    protected $description = 'Re-resolve a YouTube channel URL and save to DB';

    public function handle(): int
    {
        $channel = Channel::find($this->argument('channelId'));

        if (! $channel) {
            $this->error('Channel not found');
            return 1;
        }

        $youtubeUrl = $channel->youtube_url ?? $channel->source_url;

        if (empty($youtubeUrl)) {
            $this->error('No YouTube URL configured');
            return 1;
        }

        $yt = new YouTubeService();
        $result = $yt->verifyUrl($youtubeUrl);

        if ($result['success']) {
            $channel->source_url = $result['stream_url'];
            $channel->stream_type = 'hls';
            $channel->source_type = 'youtube';
            $channel->save();

            $this->line($result['stream_url']);
            return 0;
        }

        $this->error('Failed: ' . ($result['error'] ?? 'unknown'));
        return 1;
    }
}
