<template>
  <AdminLayout>
    <div class="p-6 max-w-5xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Add Channel</h1>
      </div>

      <form @submit.prevent="form.post(route('admin.channels.store'))" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" placeholder="Enter channel name..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
              <input v-model.number="form.channel_number" type="number" min="0" placeholder="Auto-assigned"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Channel description..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 resize-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Logo</label>
              <input v-model="form.logo_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p class="text-xs text-gray-500 mt-1">200x200 recommended</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
              <select v-model="form.genre"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select genre...</option>
                <option value="Sports">Sports</option>
                <option value="Entertainment">Entertainment</option>
                <option value="Movies">Movies</option>
                <option value="News">News</option>
                <option value="Documentary">Documentary</option>
                <option value="Kids">Kids</option>
                <option value="Music">Music</option>
                <option value="Education">Education</option>
                <option value="Religious">Religious</option>
                <option value="General">General</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
              <select v-model="form.country"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select country...</option>
                <option value="US">United States</option>
                <option value="GB">United Kingdom</option>
                <option value="FR">France</option>
                <option value="DE">Germany</option>
                <option value="ES">Spain</option>
                <option value="IT">Italy</option>
                <option value="BR">Brazil</option>
                <option value="AR">Argentina</option>
                <option value="CA">Canada</option>
                <option value="AU">Australia</option>
                <option value="IN">India</option>
                <option value="JP">Japan</option>
                <option value="CN">China</option>
                <option value="RU">Russia</option>
                <option value="ZA">South Africa</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
              <select v-model="form.language"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select language...</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
                <option value="ru">Russian</option>
                <option value="ar">Arabic</option>
                <option value="hi">Hindi</option>
                <option value="zh">Chinese</option>
                <option value="ja">Japanese</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Stream Configuration -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Stream Configuration</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream Source Type</label>
              <select v-model="form.source_type"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
                @change="onSourceTypeChange">
                <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                <option value="youtube">YouTube Live Channel</option>
              </select>
            </div>
            <div v-if="form.source_type !== 'youtube'" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL <span class="text-red-500">*</span></label>
              <input v-model="form.stream_url" type="text" placeholder="https://... or udp://@239.0.0.1:32768"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.stream_url" class="text-red-400 text-sm mt-1">{{ form.errors.stream_url }}</p>
            </div>
            <div v-if="form.source_type === 'youtube'" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">YouTube Channel URL <span class="text-red-500">*</span></label>
              <input v-model="form.youtube_url" type="url" placeholder="https://www.youtube.com/@channel or https://www.youtube.com/channel/..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.youtube_url" class="text-red-400 text-sm mt-1">{{ form.errors.youtube_url }}</p>
              <button v-if="form.youtube_url" @click="verifyYouTube" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                <svg v-if="youtubeVerifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                {{ youtubeVerifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
              </button>
              <p v-if="youtubeVerified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
              <p v-if="youtubeVerifyError" class="text-red-400 text-sm mt-1">{{ youtubeVerifyError }}</p>
            </div>
            <div v-if="form.source_type === 'youtube'" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL (resolved)</label>
              <input v-model="form.stream_url" type="text" placeholder="Resolved YouTube HLS stream URL"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" disabled />
              <p class="text-xs text-gray-500 mt-1">Automatically resolved when YouTube URL is verified.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Program Number</label>
              <input v-model.number="form.program_number" type="number" min="1" placeholder="TS program (multi-channel multicast)"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
<p class="text-xs text-gray-500 mt-1">Leave empty for single-channel sources.</p>
              <button @click="scanMulticast" class="mt-2 px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2z"/></svg>
                Scan
              </button>
</div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Local Interface</label>
              <input v-model="form.local_address" type="text" placeholder="NIC IP for multicast join, e.g. 192.168.1.50"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p class="text-xs text-gray-500 mt-1">Only used for udp:// multicast sources.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
              <select v-model="form.stream_type"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
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
              <select v-model="form.quality"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="4k">4K</option>
                <option value="1080p">FHD (1080p)</option>
                <option value="720p">HD (720p)</option>
                <option value="480p">SD (480p)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 1</label>
              <select v-model="backupState[1].type" @change="onBackupTypeChange(1)"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 mb-2">
                <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                <option value="youtube">YouTube Live Channel</option>
              </select>
              <template v-if="backupState[1].type === 'youtube'">
                <input v-model="form.youtube_url_1" type="url" placeholder="https://www.youtube.com/@channel"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                <button v-if="form.youtube_url_1" @click="verifyBackupYouTube(1)" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                  <svg v-if="backupState[1].verifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                  {{ backupState[1].verifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
                </button>
                <p v-if="backupState[1].verified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
                <p v-if="backupState[1].error" class="text-red-400 text-sm mt-1">{{ backupState[1].error }}</p>
                <p v-if="form.backup_url_1" class="text-xs text-gray-500 mt-1">Resolved stream: <span class="text-gray-400 break-all">{{ form.backup_url_1 }}</span></p>
              </template>
              <input v-else v-model="form.backup_url_1" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 2</label>
              <select v-model="backupState[2].type" @change="onBackupTypeChange(2)"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 mb-2">
                <option value="stream">Direct Stream (HLS/RTMP/RTSP/UDP)</option>
                <option value="youtube">YouTube Live Channel</option>
              </select>
              <template v-if="backupState[2].type === 'youtube'">
                <input v-model="form.youtube_url_2" type="url" placeholder="https://www.youtube.com/@channel"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                <button v-if="form.youtube_url_2" @click="verifyBackupYouTube(2)" class="mt-2 px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition inline-flex items-center gap-1">
                  <svg v-if="backupState[2].verifying" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4 12a8 8 0 0 1 8-8"/></svg>
                  {{ backupState[2].verifying ? 'Verifying...' : 'Verify & Bypass Robot Check' }}
                </button>
                <p v-if="backupState[2].verified" class="text-emerald-400 text-sm mt-1">✓ YouTube verified — cookies stored for automatic bypass</p>
                <p v-if="backupState[2].error" class="text-red-400 text-sm mt-1">{{ backupState[2].error }}</p>
                <p v-if="form.backup_url_2" class="text-xs text-gray-500 mt-1">Resolved stream: <span class="text-gray-400 break-all">{{ form.backup_url_2 }}</span></p>
              </template>
              <input v-else v-model="form.backup_url_2" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
              <input v-model.number="form.bitrate" type="number" min="0" placeholder="e.g. 5000"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- EPG Configuration -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">EPG Configuration</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG Source</label>
              <select v-model="form.epg_source_id"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option :value="null">None</option>
                <option v-for="src in epgSources" :key="src.id" :value="src.id">{{ src.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG ID</label>
              <input v-model="form.epg_id" type="text" placeholder="Channel ID from EPG source"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG Language</label>
              <select v-model="form.epg_language"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Default</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Offset</label>
              <select v-model="form.timezone_offset"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">UTC+0</option>
                <option value="-12:00">UTC-12</option>
                <option value="-11:00">UTC-11</option>
                <option value="-10:00">UTC-10</option>
                <option value="-09:00">UTC-9</option>
                <option value="-08:00">UTC-8</option>
                <option value="-07:00">UTC-7</option>
                <option value="-06:00">UTC-6</option>
                <option value="-05:00">UTC-5</option>
                <option value="-04:00">UTC-4</option>
                <option value="-03:00">UTC-3</option>
                <option value="-02:00">UTC-2</option>
                <option value="-01:00">UTC-1</option>
                <option value="+01:00">UTC+1</option>
                <option value="+02:00">UTC+2</option>
                <option value="+03:00">UTC+3</option>
                <option value="+04:00">UTC+4</option>
                <option value="+05:00">UTC+5</option>
                <option value="+05:30">UTC+5:30</option>
                <option value="+06:00">UTC+6</option>
                <option value="+07:00">UTC+7</option>
                <option value="+08:00">UTC+8</option>
                <option value="+09:00">UTC+9</option>
                <option value="+10:00">UTC+10</option>
                <option value="+11:00">UTC+11</option>
                <option value="+12:00">UTC+12</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Categorization -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Categorization</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Categories <span class="text-red-500">*</span></label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="cat.id" v-model="form.category_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ cat.name }}
                </label>
              </div>
              <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="b.id" v-model="form.bouquet_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ b.name }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Transcoding Settings -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Transcoding Settings</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.transcoding_enabled"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Enable Transcoding</span>
            </label>
            <div v-if="form.transcoding_enabled" class="grid grid-cols-1 md:grid-cols-4 gap-4 ml-6">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile</label>
                <select v-model="form.transcoding_profile"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Auto</option>
                  <option value="low_latency">Low Latency</option>
                  <option value="high_quality">High Quality</option>
                  <option value="balanced">Balanced</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
                <select v-model="form.transcoding_resolution"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="1080p">1080p</option>
                  <option value="720p">720p</option>
                  <option value="480p">480p</option>
                  <option value="360p">360p</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
                <select v-model="form.transcoding_video_codec"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="h264">H.264</option>
                  <option value="h265">H.265 / HEVC</option>
                  <option value="vp9">VP9</option>
                  <option value="av1">AV1</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
                <select v-model="form.transcoding_audio_codec"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="aac">AAC</option>
                  <option value="mp3">MP3</option>
                  <option value="ac3">AC3 / Dolby Digital</option>
                  <option value="eac3">E-AC3 / Dolby Digital Plus</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Access Control -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Access Control</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_free"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Available to all users</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Restricted Packages</label>
              <div class="space-y-2 max-h-32 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="pkg in packages" :key="pkg.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="pkg.id" v-model="form.restricted_package_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ pkg.name }}
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1">Only selected packages will have access when "Available to all" is unchecked.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction</label>
              <input v-model="form.ip_restriction" type="text" placeholder="Comma-separated IPs or CIDR ranges"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_adult"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Adult Content (requires PIN)</span>
            </label>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-between gap-3">
          <button type="button" @click="testStream"
            :disabled="!form.stream_url || testLoading"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2">
            <span v-if="testLoading" class="animate-spin">⟳</span>
            <span v-else>Test Stream</span>
          </button>
          <div class="flex gap-3">
            <Link :href="route('admin.channels.index')"
              class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
              Cancel
            </Link>
            <button type="submit" :disabled="form.processing"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
              <span>{{ form.processing ? 'Adding...' : 'Add Channel' }}</span>
            </button>
          </div>
        </div>
        <div v-if="testResult" class="text-sm" :class="testResult.success ? 'text-green-400' : 'text-red-400'">
          {{ testResult.message }}
        </div>
       </form>

       <!-- Scan Multicast Modal -->
       <Modal :show="showScanModal" @close="showScanModal = false" max-width="md">
         <div class="p-6">
           <h3 class="text-lg font-semibold text-white mb-4">Scan Multicast Stream</h3>
           <div v-if="scanLoading" class="text-center">
             <div class="animate-spin text-indigo-500 mb-4"></div>
             <p>Scanning stream...</p>
           </div>
           <div v-else="scanLoading" class="space-y-4">
             <p class="text-sm text-gray-400">Stream URL: {{ form.stream_url }}</p>
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
import { ref, reactive } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  epgSources: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
  transcodingProfiles: { type: Array, default: () => [] },
})

const testLoading = ref(false)
const testResult = ref(null)
const youtubeVerifying = ref(false)
const youtubeVerified = ref(false)
const youtubeVerifyError = ref('')

const onSourceTypeChange = () => {
  if (form.source_type !== 'youtube') {
    form.youtube_url = ''
    youtubeVerified.value = false
    youtubeVerifyError.value = ''
  }
}

const verifyYouTube = async () => {
  if (!form.youtube_url) return
  youtubeVerifying.value = true
  youtubeVerified.value = false
  youtubeVerifyError.value = ''

  try {
    const response = await fetch(route('admin.channels.verify-youtube', { channel: 0 }), {
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
      youtubeVerified.value = true
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
  1: { type: 'stream', verifying: false, verified: false, error: '' },
  2: { type: 'stream', verifying: false, verified: false, error: '' },
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
    const response = await fetch(route('admin.channels.verify-youtube', { channel: 0 }), {
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

const form = useForm({
  name: '',
  channel_number: null,
  description: '',
  logo_url: '',
  genre: '',
  country: '',
  language: '',
  stream_url: '',
  source_type: 'stream',
  youtube_url: '',
  youtube_cookies: null,
  youtube_url_1: '',
  youtube_url_1_verified: false,
  youtube_url_2: '',
  youtube_url_2_verified: false,
  stream_type: 'hls',
  program_number: null,
  local_address: '',
  backup_url_1: '',
  backup_url_2: '',
  quality: '1080p',
  bitrate: null,
  epg_id: '',
  epg_source_id: null,
  epg_language: '',
  timezone_offset: '',
  category_ids: [],
  bouquet_ids: [],
  transcoding_enabled: false,
  transcoding_profile: '',
  transcoding_resolution: '',
  transcoding_video_codec: '',
  transcoding_audio_codec: '',
  is_active: true,
  is_free: false,
  is_adult: false,
  ip_restriction: '',
  restricted_package_ids: [],
})

const testStream = () => {
  if (!form.stream_url) return
  testLoading.value = true
  testResult.value = null

  router.post(route('admin.channels.test-stream', { channel: 0 }), {
    stream_url: form.stream_url,
    source_type: form.source_type,
    youtube_url: form.youtube_url,
  }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      testResult.value = {
        success: true,
        message: 'Stream URL is reachable and responding.',
      }
      testLoading.value = false
    },
    onError: (errors) => {
      testResult.value = {
        success: false,
        message: 'Stream test failed. Check the URL and try again.',
      }
      testLoading.value = false
    },
  })
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