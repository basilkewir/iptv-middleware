<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Edit Channel: {{ channel?.name }}</h1>
      </div>

      <form @submit.prevent="form.put(route('admin.channels.update', { channel: props.channel.id }))" class="space-y-6">
        <!-- Basic Information -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name *</label>
                <input v-model="form.name" type="text" class="input-field" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
                <input v-model.number="form.channel_number" type="number" class="input-field" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" class="input-field" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Logo URL</label>
                <input v-model="form.logo_url" type="url" class="input-field" placeholder="https://example.com/logo.png (200x200 recommended)" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
                <select v-model="form.genre" class="input-field">
                  <option value="">Select Genre</option>
                  <option value="sports">Sports</option>
                  <option value="entertainment">Entertainment</option>
                  <option value="news">News</option>
                  <option value="movies">Movies</option>
                  <option value="kids">Kids</option>
                  <option value="music">Music</option>
                  <option value="documentary">Documentary</option>
                  <option value="lifestyle">Lifestyle</option>
                  <option value="religious">Religious</option>
                  <option value="international">International</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                <select v-model="form.country" class="input-field">
                  <option value="">Select Country</option>
                  <option value="US">United States</option>
                  <option value="UK">United Kingdom</option>
                  <option value="CA">Canada</option>
                  <option value="AU">Australia</option>
                  <option value="DE">Germany</option>
                  <option value="FR">France</option>
                  <option value="IN">India</option>
                  <option value="BR">Brazil</option>
                  <option value="JP">Japan</option>
                  <option value="CN">China</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
                <select v-model="form.language" class="input-field">
                  <option value="">Select Language</option>
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                  <option value="pt">Portuguese</option>
                  <option value="ar">Arabic</option>
                  <option value="hi">Hindi</option>
                  <option value="ja">Japanese</option>
                  <option value="zh">Chinese</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Stream Configuration -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Stream Configuration</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream Source Type</label>
                <select v-model="form.source_type" class="input-field" @change="onSourceTypeChange">
                  <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                  <option value="youtube">YouTube Live Channel</option>
                </select>
              </div>
              <div v-if="form.source_type === 'youtube'" class="col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">YouTube Channel URL</label>
                <input v-model="form.youtube_url" type="url" class="input-field" placeholder="https://www.youtube.com/@channel or https://www.youtube.com/channel/..." />
                <p v-if="form.errors.youtube_url" class="text-red-400 text-sm mt-1">{{ form.errors.youtube_url }}</p>
                <button v-if="form.youtube_url && !form.youtube_verified" @click="verifyYouTube" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                  <svg v-if="youtubeVerifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                  {{ youtubeVerifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
                </button>
                <p v-if="form.youtube_verified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
                <p v-if="youtubeVerifyError" class="text-red-400 text-sm mt-1">{{ youtubeVerifyError }}</p>
              </div>
              <div v-if="form.source_type === 'youtube'" class="col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL (resolved)</label>
                <input v-model="form.stream_url" type="text" class="input-field" placeholder="Resolved YouTube HLS stream URL" disabled />
                <p class="text-xs text-gray-500 mt-1">Automatically resolved when YouTube URL is verified.</p>
              </div>
              <div v-if="form.source_type !== 'youtube'">
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL *</label>
                <input v-model="form.stream_url" type="text" class="input-field" placeholder="https://... or udp://@239.0.0.1:32768" />
                <p v-if="form.errors.stream_url" class="text-red-400 text-sm mt-1">{{ form.errors.stream_url }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Program Number</label>
                <input v-model.number="form.program_number" type="number" min="1" class="input-field" placeholder="TS program (multi-channel multicast)" />
                <p class="text-xs text-gray-500 mt-1">Leave empty for single-channel sources.</p>
              <button @click="scanMulticast" class="mt-2 px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2z"/></svg>
                Scan
              </button>
</div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Local Interface</label>
                <input v-model="form.local_address" type="text" class="input-field" placeholder="NIC IP for multicast join, e.g. 192.168.1.50" />
                <p class="text-xs text-gray-500 mt-1">Only used for udp:// multicast sources.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
                <select v-model="form.stream_type" class="input-field">
                  <option value="hls">HLS</option>
                  <option value="rtmp">RTMP</option>
                  <option value="rtsp">RTSP</option>
                  <option value="udp">UDP</option>
                  <option value="dash">DASH</option>
                  <option value="m3u8">M3U8</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Quality</label>
                <select v-model="form.quality" class="input-field">
                  <option value="4k">4K</option>
                  <option value="1080p">FHD</option>
                  <option value="720p">HD</option>
                  <option value="480p">SD</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 1</label>
                <select v-model="backupState[1].type" @change="onBackupTypeChange(1)" class="input-field mb-2">
                  <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                  <option value="youtube">YouTube Live Channel</option>
                </select>
                <template v-if="backupState[1].type === 'youtube'">
                  <input v-model="form.youtube_url_1" type="url" class="input-field" placeholder="https://www.youtube.com/@channel" />
                  <button v-if="form.youtube_url_1 && !backupState[1].verified" @click="verifyBackupYouTube(1)" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                    <svg v-if="backupState[1].verifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                    {{ backupState[1].verifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
                  </button>
                  <p v-if="backupState[1].verified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
                  <p v-if="backupState[1].error" class="text-red-400 text-sm mt-1">{{ backupState[1].error }}</p>
                  <p v-if="form.backup_url_1" class="text-xs text-gray-500 mt-1 break-all">Resolved stream: <span class="text-gray-400">{{ form.backup_url_1 }}</span></p>
                </template>
                <input v-else v-model="form.backup_url_1" type="url" class="input-field" placeholder="http://..." />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 2</label>
                <select v-model="backupState[2].type" @change="onBackupTypeChange(2)" class="input-field mb-2">
                  <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                  <option value="youtube">YouTube Live Channel</option>
                </select>
                <template v-if="backupState[2].type === 'youtube'">
                  <input v-model="form.youtube_url_2" type="url" class="input-field" placeholder="https://www.youtube.com/@channel" />
                  <button v-if="form.youtube_url_2 && !backupState[2].verified" @click="verifyBackupYouTube(2)" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                    <svg v-if="backupState[2].verifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                    {{ backupState[2].verifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
                  </button>
                  <p v-if="backupState[2].verified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
                  <p v-if="backupState[2].error" class="text-red-400 text-sm mt-1">{{ backupState[2].error }}</p>
                  <p v-if="form.backup_url_2" class="text-xs text-gray-500 mt-1 break-all">Resolved stream: <span class="text-gray-400">{{ form.backup_url_2 }}</span></p>
                </template>
                <input v-else v-model="form.backup_url_2" type="url" class="input-field" placeholder="http://..." />
              </div>
            </div>
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-3">
              <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                <div class="flex items-center gap-2">
                  <label class="block text-sm font-medium text-gray-300">Live Source Status</label>
                  <span v-if="probing" class="inline-flex items-center gap-1 text-xs text-cyan-400">
                    <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                    checking…
                  </span>
                  <span v-else class="text-xs text-gray-500">auto-refresh 30s · last probed {{ lastProbedLabel }}</span>
                </div>
              </div>
              <ChannelSources
                :channel="sourceStatusChannel"
                :disabled="switchingSource"
                @switch="(idx) => switchSource(idx)"
              />
              <p class="text-xs text-gray-500 mt-2">Status refreshes automatically every 30s. When the primary source is offline the system switches to a backup automatically and switches back to primary as soon as it recovers. ACTIVE marks the source currently on air.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
              <input v-model.number="form.bitrate" type="number" class="input-field" placeholder="e.g., 5000" />
            </div>
          </div>
        </div>

        <!-- EPG Configuration -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">EPG Configuration</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG Source</label>
                <select v-model="form.epg_source_id" class="input-field">
                  <option value="">Select EPG Source</option>
                  <option v-for="source in epgSources" :key="source.id" :value="source.id">{{ source.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG ID</label>
                <input v-model="form.epg_id" type="text" class="input-field" placeholder="EPG Channel ID" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG Language</label>
                <select v-model="form.epg_language" class="input-field">
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Offset</label>
                <select v-model="form.timezone_offset" class="input-field">
                  <option value="UTC+0">UTC+0</option>
                  <option value="UTC-5">UTC-5 (EST)</option>
                  <option value="UTC-6">UTC-6 (CST)</option>
                  <option value="UTC-7">UTC-7 (MST)</option>
                  <option value="UTC-8">UTC-8 (PST)</option>
                  <option value="UTC+1">UTC+1 (CET)</option>
                  <option value="UTC+5:30">UTC+5:30 (IST)</option>
                  <option value="UTC+9">UTC+9 (JST)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Categorization -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Categorization</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Categories *</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ cat.name }}
                </label>
              </div>
              <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="b.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ b.name }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Transcoding Settings -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Transcoding Settings</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.transcoding_enabled" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Enable Transcoding</span>
            </label>
            <div v-if="form.transcoding_enabled" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Transcoding Device</label>
                <select v-model="form.transcoding_device" class="input-field">
                  <option value="cpu">CPU (libx264)</option>
                  <option value="gpu">GPU (h264_nvenc)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">GPU uses NVIDIA NVENC hardware encoder (faster, lower quality)</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile</label>
                <select v-model="form.transcoding_profile" class="input-field">
                  <option value="auto">Auto</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
                <select v-model="form.transcoding_resolution" class="input-field">
                  <option value="1080p">1080p</option>
                  <option value="720p">720p</option>
                  <option value="480p">480p</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
                <select v-model="form.transcoding_video_codec" class="input-field">
                  <option value="h264">H264</option>
                  <option value="h265">H265</option>
                  <option value="vp9">VP9</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
                <select v-model="form.transcoding_audio_codec" class="input-field">
                  <option value="aac">AAC</option>
                  <option value="mp3">MP3</option>
                  <option value="opus">Opus</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Access Control -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Access Control</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.is_available_to_all" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Available to all users</span>
            </label>
            <div v-if="!form.is_available_to_all">
              <label class="block text-sm font-medium text-gray-300 mb-2">Restricted Packages</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="pkg in packages" :key="pkg.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="pkg.id" v-model="form.restricted_package_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ pkg.name }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction (comma-separated)</label>
              <input v-model="form.ip_restriction" type="text" class="input-field" placeholder="192.168.1.1, 10.0.0.0/24" />
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.is_adult" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Adult Content (requires PIN)</span>
            </label>
          </div>
        </div>

        <!-- Status -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Status</h2>
          <div class="grid grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" /> Active
            </label>
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input v-model="form.is_free" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" /> Free Channel
            </label>
          </div>
        </div>

        <div class="flex justify-between pt-4 border-t border-gray-700">
          <button
            type="button"
            @click="testStream"
            :disabled="!form.stream_url || testing"
            class="btn-secondary"
          >
            {{ testing ? 'Testing...' : 'Test Stream' }}
          </button>
          <div class="flex gap-3">
            <Link :href="route('admin.channels.index')" class="btn-secondary">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="btn-primary">
              {{ form.processing ? 'Updating...' : 'Update Channel' }}
            </button>
          </div>
        </div>
      </form>

      <!-- Test Stream Result Modal -->
      <Modal :show="showTestResult" @close="showTestResult = false" max-width="md">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-white mb-4">Stream Test Result</h3>
          <div v-if="testResult" class="space-y-4">
            <div class="bg-gray-700 rounded-lg p-4 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-gray-400 text-sm">Status</span>
                <span class="text-sm font-medium px-2 py-0.5 rounded-full"
                  :class="testResult.status === 'online' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ testResult.status === 'online' ? 'Online' : 'Offline' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-400 text-sm">URL</span>
                <span class="text-white text-sm truncate max-w-[200px]">{{ channel?.stream_url }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Type</span>
                <span class="text-white text-sm font-medium">{{ testResult.detected_type || channel?.stream_type || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Quality</span>
                <span class="text-white text-sm font-medium">{{ testResult.quality || 'Unknown' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Resolution</span>
                <span class="text-white text-sm font-medium">{{ testResult.resolution || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Codec</span>
                <span class="text-white text-sm font-medium">{{ testResult.codec || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Bitrate</span>
                <span class="text-white text-sm font-medium">{{ testResult.bitrate ? Math.round(testResult.bitrate / 1000) + ' kbps' : 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">FPS</span>
                <span class="text-white text-sm font-medium">{{ testResult.fps || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">HTTP Code</span>
                <span class="text-white text-sm font-medium">{{ testResult.http_code || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Response Time</span>
                <span class="text-white text-sm font-medium">{{ testResult.response_time }}ms</span>
              </div>
            </div>

            <div v-if="testResult.error" class="bg-red-500/10 rounded-lg p-3">
              <span class="text-red-400 text-sm">{{ testResult.error }}</span>
            </div>
          </div>
          <div class="mt-6 flex justify-end">
            <button @click="showTestResult = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition">
              Close
            </button>
          </div>
        </div>
      </Modal>

      <!-- Scan Multicast Modal -->
      <Modal :show="showScanModal" @close="showScanModal = false" max-width="md">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-white mb-4">Scan Multicast Stream</h3>
          <div v-if="scanLoading" class="text-center">
            <div class="animate-spin text-indigo-500 mb-4"></div>
            <p>Scanning stream...</p>
          </div>
          <div v-else="scanLoading" class="space-y-4">
            <p class="text-sm text-gray-400">Stream URL: {{ channel?.stream_url }}</p>
            <p v-if="scanError" class="text-red-400 text-sm">{{ scanError }}</p>
            <div v-if="programs.length > 0" class="bg-gray-700 rounded-lg p-4 max-h-80 overflow-y-auto">
              <p class="text-xs text-gray-500 mb-2">Detected Programs:</p>
              <div class="space-y-1">
                <label v-for="prog in programs" :key="prog.program_id" class="flex items-center gap-2 text-gray-300 cursor-pointer text-sm">
                  <input type="radio" :value="prog.program_id" v-model="selectedProgram" class="w-4 h-4 rounded bg-gray-700 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  <span>{{ prog.program_id }} {{ prog.name || '' }}</span>
                </label>
              </div>
            </div>
            <p v-else="!scanError" class="text-yellow-400 text-sm">No programs detected (single-channel source or scan timeout).</p>
          </div>
          <div class="mt-6 flex justify-end">
            <button @click="showScanModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition">Close</button>
            <button @click="importProgram" :disabled="!selectedProgram" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition ml-2">
              Select Program {{ selectedProgram }}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Modal from '@/Components/Common/Modal.vue'
import ChannelSources from '@/Components/ChannelSources.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  channel: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  epgSources: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
})

const form = useForm({
  name: props.channel?.name || '',
  channel_number: props.channel?.channel_number || null,
  description: props.channel?.description || '',
  logo_url: props.channel?.logo_url || '',
  genre: props.channel?.genre || '',
  country: props.channel?.country || '',
  language: props.channel?.language || '',
  stream_url: props.channel?.stream_url || '',
  source_type: props.channel?.source_type || 'stream',
  youtube_url: props.channel?.youtube_url || '',
  youtube_verified: props.channel?.youtube_verified || false,
  youtube_cookies: props.channel?.youtube_cookies || null,
  youtube_url_1: props.channel?.youtube_url_1 || '',
  youtube_url_1_verified: props.channel?.youtube_url_1_verified || false,
  youtube_url_2: props.channel?.youtube_url_2 || '',
  youtube_url_2_verified: props.channel?.youtube_url_2_verified || false,
  stream_type: props.channel?.stream_type || 'hls',
  program_number: props.channel?.program_number ?? null,
  local_address: props.channel?.local_address || '',
  backup_url_1: props.channel?.backup_url_1 || '',
  backup_url_2: props.channel?.backup_url_2 || '',
  quality: props.channel?.quality || '1080p',
  bitrate: props.channel?.bitrate || null,
  epg_id: props.channel?.epg_id || '',
  epg_source_id: props.channel?.epg_source_id || null,
  epg_language: props.channel?.epg_language || 'en',
  timezone_offset: props.channel?.timezone_offset || 'UTC+0',
  category_ids: (props.channel?.categories || []).map(c => c.id),
  bouquet_ids: (props.channel?.bouquets || []).map(b => b.id),
  transcoding_enabled: props.channel?.transcoding_enabled ?? false,
  transcoding_device: props.channel?.transcoding_device || 'cpu',
  transcoding_profile: props.channel?.transcoding_profile || 'auto',
  transcoding_resolution: props.channel?.transcoding_resolution || '1080p',
  transcoding_video_codec: props.channel?.transcoding_video_codec || 'h264',
  transcoding_audio_codec: props.channel?.transcoding_audio_codec || 'aac',
  is_active: props.channel?.is_active ?? true,
  is_free: props.channel?.is_free ?? false,
  is_adult: props.channel?.is_adult ?? false,
  is_available_to_all: props.channel?.is_available_to_all ?? true,
  ip_restriction: props.channel?.ip_restriction || '',
  restricted_package_ids: (props.channel?.restricted_packages || []).map(p => p.id),
  sort_order: props.channel?.sort_order || 0,
})

const testing = ref(false)
const showTestResult = ref(false)
const testResult = ref(null)
const youtubeVerifying = ref(false)
const youtubeVerifyError = ref('')

// ── Live source status ──
const probing = ref(false)
const switchingSource = ref(false)

const sourceStatusChannel = ref({ ...props.channel })

const lastProbedLabel = computed(() => {
  const d = sourceStatusChannel.value?.sources_last_probed_at
  if (!d) return 'never'
  const date = new Date(d)
  return isNaN(date.getTime()) ? 'never' : date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
})

const applySourceStatuses = (list) => {
  if (!Array.isArray(list)) return
  sourceStatusChannel.value = { ...sourceStatusChannel.value, source_statuses: list }
}

const probeSources = async () => {
  probing.value = true
  try {
    const response = await fetch(route('admin.channels.probe-sources', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
    })
    const data = await response.json()
    if (data.success) {
      applySourceStatuses(data.data.source_statuses)
      sourceStatusChannel.value.active_source_index = data.data.active_source_index
      sourceStatusChannel.value.sources_last_probed_at = data.data.last_checked_at
    }
  } catch (e) {
    console.error('Source probe failed:', e)
  } finally {
    probing.value = false
  }
}

const switchSource = async (sourceIndex) => {
  switchingSource.value = true
  try {
    const response = await fetch(route('admin.channels.switch-source', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
      body: JSON.stringify({ source_index: sourceIndex }),
    })
    const data = await response.json()
    if (data.success) {
      applySourceStatuses(data.data.source_statuses)
      sourceStatusChannel.value.active_source_index = data.data.active_source_index
    }
  } catch (e) {
    console.error('Source switch failed:', e)
  } finally {
    switchingSource.value = false
  }
}

// Auto-refresh live source status every 30s.
let statusInterval = null
onMounted(() => {
  probeSources()
  statusInterval = setInterval(probeSources, 30000)
})
onUnmounted(() => {
  if (statusInterval) clearInterval(statusInterval)
})

const onSourceTypeChange = () => {
  if (form.source_type !== 'youtube') {
    form.youtube_url = ''
    youtubeVerifyError.value = ''
  }
}

const verifyYouTube = async () => {
  if (!form.youtube_url) return
  youtubeVerifying.value = true
  youtubeVerifyError.value = ''

  try {
    const response = await fetch(route('admin.channels.verify-youtube', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
      body: JSON.stringify({ youtube_url: form.youtube_url }),
    })
    const data = await response.json()
    if (data.success) {
      form.stream_url = data.data.stream_url
      form.source_type = 'youtube'
      form.youtube_url = data.data.channel_id ? `https://www.youtube.com/channel/${data.data.channel_id}` : form.youtube_url
      form.youtube_verified = true
    } else {
      youtubeVerifyError.value = data.message || 'Verification failed'
    }
  } catch (e) {
    youtubeVerifyError.value = 'Network error. Please try again.'
  } finally {
    youtubeVerifying.value = false
  }
}

const backupState = reactive({
  1: { type: props.channel?.youtube_url_1 ? 'youtube' : 'stream', verifying: false, verified: !!props.channel?.youtube_url_1_verified, error: '' },
  2: { type: props.channel?.youtube_url_2 ? 'youtube' : 'stream', verifying: false, verified: !!props.channel?.youtube_url_2_verified, error: '' },
})

const onBackupTypeChange = (index) => {
  const state = backupState[index]
  state.verifying = false
  state.verified = false
  state.error = ''
  if (state.type !== 'youtube') {
    form[`youtube_url_${index}`] = ''
  }
}

const verifyBackupYouTube = async (index) => {
  const ytUrl = form[`youtube_url_${index}`]
  if (!ytUrl) return
  const state = backupState[index]
  state.verifying = true
  state.verified = false
  state.error = ''

  try {
    const response = await fetch(route('admin.channels.verify-youtube', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
      body: JSON.stringify({ youtube_url: ytUrl }),
    })
    const data = await response.json()
    if (data.success) {
      form[`backup_url_${index}`] = data.data.stream_url
      form[`youtube_url_${index}_verified`] = true
      state.verified = true
    } else {
      state.error = data.message || 'Verification failed'
    }
  } catch (e) {
    state.error = 'Network error. Please try again.'
  } finally {
    state.verifying = false
  }
}

const testStream = async () => {
  if (!form.stream_url) return

  testing.value = true
  try {
    const response = await fetch(route('admin.channels.test-stream', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({ stream_url: form.stream_url, source_type: form.source_type, youtube_url: form.youtube_url }),
    })
    const data = await response.json()
    testResult.value = data.data || data
    showTestResult.value = true
  } catch (error) {
    testResult.value = {
      status: 'error',
      error: error.message,
    }
    showTestResult.value = true
  } finally {
    testing.value = false
  }
}

const showScanModal = ref(false)
const scanLoading = ref(false)
const scanError = ref('')
const programs = ref([])
const selectedProgram = ref(null)

const scanMulticast = () => {
  if (!form.stream_url) return
  showScanModal.value = true
  scanLoading.value = true
  scanError.value = ''
  programs.value = []
  selectedProgram.value = null

  router.post(route('admin.channels.scan-multicast', { url: form.stream_url }), {}, {
    preserveState: true,
    onSuccess: (page) => {
      showScanModal.value = true
      scanLoading.value = false
      const data = page.props?.data || {}
      programs.value = data.programs || []
      if (programs.value.length > 0) {
        selectedProgram.value = programs.value[0].program_id
      }
    },
    onError: (errors) => {
      showScanModal.value = true
      scanLoading.value = false
      scanError.value = 'Scan failed. Check the URL and try again.'
      programs.value = []
    },
  })
}

const importProgram = () => {
  if (selectedProgram === null) return
  form.program_number = selectedProgram
  showScanModal.value = false
}
</script>
