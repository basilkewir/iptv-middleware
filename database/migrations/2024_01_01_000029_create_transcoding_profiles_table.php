<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcoding_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('profile_type')->default('video'); // video, audio, adaptation
            $table->string('resolution')->nullable(); // 480p, 720p, 1080p, 2160p
            $table->string('video_codec')->default('h264'); // h264, h265, vp9
            $table->integer('bitrate')->nullable(); // kbps
            $table->integer('frame_rate')->nullable();
            $table->integer('keyframe_interval')->nullable();
            $table->string('pixel_format')->nullable();
            $table->string('color_space')->nullable();
            $table->string('profile')->nullable(); // baseline, main, high
            $table->string('level')->nullable();
            $table->string('preset')->default('medium'); // ultrafast, superfast, fast, medium, slow, veryslow
            $table->string('tune')->nullable(); // film, animation, grain, stillimage
            $table->integer('crf')->nullable();
            $table->string('audio_codec')->default('aac'); // aac, mp3, ac3, eac3
            $table->integer('audio_bitrate')->nullable(); // kbps
            $table->integer('sample_rate')->nullable();
            $table->string('channels')->nullable(); // mono, stereo, 5.1, 7.1
            $table->string('audio_language')->nullable();
            $table->integer('hls_segment_duration')->default(6);
            $table->string('hls_playlist_type')->nullable();
            $table->string('dash_profile')->nullable();
            $table->boolean('gpu_acceleration')->default(false);
            $table->string('gpu_type')->nullable(); // nvidia, intel, amd
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('transcoding_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('transcoding_profiles')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vod_content_id')->nullable()->constrained('vod_content')->nullOnDelete();
            $table->string('job_type')->default('live'); // live, vod, batch, scheduled, custom
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->integer('priority')->default(0);
            $table->string('input_url')->nullable();
            $table->string('output_url')->nullable();
            $table->integer('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->text('logs')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transcoding_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('transcoding_profiles')->cascadeOnDelete();
            $table->string('filter_type')->default('video'); // video, audio
            $table->string('filter_name'); // crop, scale, rotate, flip, deinterlace, denoise, sharpen, etc.
            $table->json('parameters')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcoding_filters');
        Schema::dropIfExists('transcoding_jobs');
        Schema::dropIfExists('transcoding_profiles');
    }
};
