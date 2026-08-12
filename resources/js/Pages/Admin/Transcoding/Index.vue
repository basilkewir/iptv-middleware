<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Transcoding Profiles</h1>
          <p class="text-gray-400 text-sm mt-1">Manage transcoding configurations</p>
        </div>
        <Link :href="route('admin.transcoding.create')" class="btn-primary flex items-center gap-2">
          <Plus class="w-4 h-4" /> Create Profile
        </Link>
      </div>

      <div class="mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search profiles by name..."
          class="input-field w-full md:w-96"
        />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="profile in filteredProfiles" :key="profile.id" class="card hover:border-gray-600 transition">
          <div class="flex items-start justify-between mb-3">
            <div>
              <h3 class="text-white font-semibold">{{ profile.name }}</h3>
              <p class="text-gray-500 text-sm">{{ profile.description || 'No description' }}</p>
            </div>
            <button
              @click="toggleProfile(profile)"
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
              :class="profile.is_active ? 'bg-green-500' : 'bg-gray-600'"
            >
              <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="profile.is_active ? 'translate-x-5' : 'translate-x-0'" />
            </button>
          </div>

          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between">
              <span class="text-gray-400">Resolution:</span>
              <span class="text-white">{{ profile.resolution || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Video Codec:</span>
              <span class="text-white">{{ profile.video_codec || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Audio Codec:</span>
              <span class="text-white">{{ profile.audio_codec || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Preset:</span>
              <span class="text-white">{{ profile.preset || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">GPU:</span>
              <span class="text-white">{{ profile.gpu_acceleration ? profile.gpu_type : 'Disabled' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Jobs:</span>
              <span class="text-white">{{ profile.jobs_count || 0 }}</span>
            </div>
          </div>

          <div class="flex items-center gap-2 pt-3 border-t border-gray-700">
            <Link :href="route('admin.transcoding.edit', profile.id)" class="flex-1 btn-secondary text-center text-sm py-1.5">
              Edit
            </Link>
            <button @click="deleteProfile(profile)" class="p-2 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded">
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div v-if="filteredProfiles.length === 0" class="col-span-3 card text-center py-12">
          <Search class="w-12 h-12 text-gray-600 mx-auto mb-4" />
          <p class="text-gray-500">{{ search ? 'No profiles match your search.' : 'No transcoding profiles yet.' }}</p>
          <Link v-if="!search" :href="route('admin.transcoding.create')" class="btn-primary mt-4 inline-flex items-center gap-2">
            <Plus class="w-4 h-4" /> Create Your First Profile
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed } from 'vue'
import { Plus, Trash2, Search } from 'lucide-vue-next'

const props = defineProps({
  profiles: { type: Array, default: () => [] },
})

const search = ref('')

const filteredProfiles = computed(() => {
  if (!search.value) return props.profiles
  const q = search.value.toLowerCase()
  return props.profiles.filter(p => p.name.toLowerCase().includes(q))
})

const deleteProfile = (profile) => {
  if (confirm(`Delete "${profile.name}"?`)) {
    router.delete(route('admin.transcoding.destroy', profile.id))
  }
}

const toggleProfile = (profile) => {
  router.put(route('admin.transcoding.update', profile.id), {
    is_active: !profile.is_active,
  }, { preserveState: true })
}
</script>
