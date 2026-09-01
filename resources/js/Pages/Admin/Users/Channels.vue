<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.users.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Users
        </Link>
        <h1 class="text-2xl font-bold text-white">{{ user.username }}</h1>
        <p class="text-gray-400 mt-1">Choose which My Channels this user can see and manage.</p>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-900/30 border border-green-700/50 rounded-lg text-green-400 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-5">
        <div class="flex items-center justify-between">
          <label class="block text-sm font-medium text-gray-300">My Channels</label>
          <div class="flex items-center gap-3 text-sm">
            <button type="button" @click="selectAll" class="text-indigo-400 hover:text-indigo-300">Select all</button>
            <button type="button" @click="selected = []" class="text-gray-400 hover:text-white">Clear</button>
          </div>
        </div>

        <div class="space-y-2 max-h-[480px] overflow-y-auto border border-gray-700 rounded-lg p-3 bg-gray-900/40">
          <label v-for="ch in channels" :key="ch.id" class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition hover:bg-gray-700"
            :class="selected.includes(ch.id) ? 'bg-indigo-900/20 border border-indigo-700/40' : 'border border-transparent'">
            <input type="checkbox" :value="ch.id" v-model="selected" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-sm text-gray-200">{{ ch.channel_name }}</span>
          </label>

          <p v-if="channels.length === 0" class="text-center py-8 text-gray-500">No My Channels available. Create one first.</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.users.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ saving ? 'Saving...' : 'Save Channel Access' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  user: { type: Object, default: () => ({}) },
  channels: { type: Array, default: () => [] },
  assigned_channel_ids: { type: Array, default: () => [] },
})

const selected = ref([...props.assigned_channel_ids])
const saving = ref(false)

const selectAll = () => {
  selected.value = props.channels.map(c => c.id)
}

const submit = () => {
  saving.value = true
  router.put(route('admin.users.channels.update', props.user.id), {
    channel_ids: selected.value,
  }, {
    preserveScroll: true,
    onFinish: () => { saving.value = false },
  })
}
</script>