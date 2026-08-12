<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Backup Settings</h1>
        <p class="text-gray-400 mt-1">Configure backup schedule, content, and storage</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.backup.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Backup Schedule</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_automatic_backups" type="checkbox" id="enable_automatic_backups" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_automatic_backups" class="text-gray-300">Enable Automatic Backups</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup Schedule</label>
              <div class="flex gap-4 mt-2">
                <div v-for="s in scheduleOptions" :key="s.value" class="flex items-center gap-2">
                  <input v-model="form.backup_schedule" :value="s.value" type="radio" :id="'schedule_' + s.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'schedule_' + s.value" class="text-gray-300">{{ s.label }}</label>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Backup Time</label>
                <input v-model="form.backup_time" type="time" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div v-if="form.backup_schedule === 'custom'">
                <label class="block text-sm font-medium text-gray-300 mb-2">Custom CRON Expression</label>
                <input v-model="form.backup_cron" type="text" placeholder="*/5 * * * *" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Retention Period (days)</label>
                <input v-model="form.backup_retention" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Backups</label>
                <input v-model="form.max_backups" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Backup Content</h3>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="item in contentOptions" :key="item.key" class="flex items-center gap-3">
              <input v-model="form.backup_content" :value="item.key" type="checkbox" :id="item.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label :for="item.key" class="text-gray-300">{{ item.label }}</label>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Storage</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Primary Storage</label>
              <div class="flex gap-4 mt-2">
                <div v-for="s in storageOptions" :key="s.value" class="flex items-center gap-2">
                  <input v-model="form.primary_storage" :value="s.value" type="radio" :id="'storage_' + s.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'storage_' + s.value" class="text-gray-300">{{ s.label }}</label>
                </div>
              </div>
            </div>
            <div v-if="form.primary_storage === 'local'">
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup Path</label>
              <input v-model="form.backup_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div v-if="form.primary_storage === 's3'" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">S3 Bucket</label>
                <input v-model="form.s3_bucket" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Access Key</label>
                  <input v-model="form.s3_access_key" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Secret Key</label>
                  <input v-model="form.s3_secret_key" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.compress_backups" type="checkbox" id="compress_backups" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="compress_backups" class="text-gray-300">Compress Backups</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.encrypt_backups" type="checkbox" id="encrypt_backups" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="encrypt_backups" class="text-gray-300">Encrypt Backups</label>
            </div>
            <div v-if="form.encrypt_backups">
              <label class="block text-sm font-medium text-gray-300 mb-2">Encryption Key</label>
              <input v-model="form.encryption_key" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Restore</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Available Backups</label>
              <div class="overflow-hidden rounded-lg border border-gray-600">
                <table class="w-full">
                  <thead>
                    <tr class="bg-gray-700/50">
                      <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Filename</th>
                      <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Size</th>
                      <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Date</th>
                      <th class="px-4 py-3 text-center text-sm font-medium text-gray-300">Action</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-600">
                    <tr v-for="(backup, index) in availableBackups" :key="index" class="hover:bg-gray-700/30">
                      <td class="px-4 py-3 text-sm text-white font-mono">{{ backup.filename }}</td>
                      <td class="px-4 py-3 text-sm text-gray-400">{{ backup.size }}</td>
                      <td class="px-4 py-3 text-sm text-gray-400">{{ backup.date }}</td>
                      <td class="px-4 py-3 text-center">
                        <button type="button" @click="restoreBackup(backup)" class="px-3 py-1 bg-yellow-600 hover:bg-yellow-500 text-white rounded text-sm transition">Restore</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <p v-if="availableBackups.length === 0" class="text-gray-500 text-sm text-center py-4">No backups available</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Restore Mode</label>
                <select v-model="form.restore_mode" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="full">Full Restore</option>
                  <option value="selective">Selective Restore</option>
                  <option value="merge">Merge</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Restore Options</label>
                <div class="flex gap-4 mt-2">
                  <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                    <input v-model="form.restore_confirm" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    Confirm Before
                  </label>
                  <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                    <input v-model="form.restore_backup_before" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    Backup Before
                  </label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Restore Progress</label>
              <div class="w-full bg-gray-700 rounded-full h-4">
                <div class="bg-indigo-600 h-4 rounded-full transition-all duration-500" :style="{ width: restoreProgress + '%' }"></div>
              </div>
              <p class="text-gray-400 text-sm mt-1">{{ restoreProgress === 0 ? 'Ready' : restoreProgress + '%' }}</p>
            </div>
          </div>
        </div>

        <div class="flex justify-between">
          <button type="button" @click="backupNow" class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition">Backup Now</button>
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const scheduleOptions = [
  { value: 'hourly', label: 'Hourly' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'custom', label: 'Custom' },
]

const contentOptions = [
  { key: 'db_full', label: 'Database Full' },
  { key: 'db_structure', label: 'Structure' },
  { key: 'db_data', label: 'Data' },
  { key: 'user_files', label: 'User Files' },
  { key: 'config', label: 'Config' },
  { key: 'channels', label: 'Channels' },
  { key: 'epg', label: 'EPG' },
  { key: 'vod_metadata', label: 'VOD Metadata' },
  { key: 'logs', label: 'Logs' },
  { key: 'cache', label: 'Cache' },
]

const storageOptions = [
  { value: 'local', label: 'Local' },
  { value: 's3', label: 'Amazon S3' },
  { value: 'gcs', label: 'Google Cloud' },
  { value: 'do', label: 'DigitalOcean Spaces' },
  { value: 'ftp', label: 'FTP/SFTP' },
]

const restoreProgress = ref(0)

const availableBackups = ref(props.settings.available_backups || [
  { filename: 'backup_2026-07-28_020000.tar.gz', size: '124 MB', date: '2026-07-28 02:00' },
  { filename: 'backup_2026-07-27_020000.tar.gz', size: '118 MB', date: '2026-07-27 02:00' },
  { filename: 'backup_2026-07-26_020000.tar.gz', size: '121 MB', date: '2026-07-26 02:00' },
])

const form = useForm({
  enable_automatic_backups: props.settings.enable_automatic_backups ?? true,
  backup_schedule: props.settings.backup_schedule || 'daily',
  backup_time: props.settings.backup_time || '02:00',
  backup_cron: props.settings.backup_cron || '',
  backup_retention: props.settings.backup_retention || 30,
  max_backups: props.settings.max_backups || 30,
  backup_content: props.settings.backup_content || ['db_full', 'config', 'channels', 'epg'],
  primary_storage: props.settings.primary_storage || 'local',
  backup_path: props.settings.backup_path || 'storage/backups',
  compress_backups: props.settings.compress_backups ?? true,
  encrypt_backups: props.settings.encrypt_backups ?? false,
  encryption_key: props.settings.encryption_key || '',
  s3_bucket: props.settings.s3_bucket || '',
  s3_access_key: props.settings.s3_access_key || '',
  s3_secret_key: props.settings.s3_secret_key || '',
  restore_mode: props.settings.restore_mode || 'full',
  restore_confirm: props.settings.restore_confirm ?? true,
  restore_backup_before: props.settings.restore_backup_before ?? true,
})

function backupNow() {
  if (confirm('Start a backup now?')) {
    router.post(route('admin.settings.backup.run'))
  }
}

function restoreBackup(backup) {
  if (confirm(`Restore from "${backup.filename}"? ${form.restore_backup_before ? 'A backup will be created first.' : ''}`)) {
    restoreProgress.value = 0
    const interval = setInterval(() => {
      restoreProgress.value += 5
      if (restoreProgress.value >= 100) {
        clearInterval(interval)
        router.post(route('admin.settings.backup.restore'), { filename: backup.filename, mode: form.restore_mode })
      }
    }, 200)
  }
}
</script>
