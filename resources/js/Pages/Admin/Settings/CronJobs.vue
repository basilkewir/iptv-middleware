<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Cron Jobs</h1>
        <p class="text-gray-400 mt-1">Manage scheduled tasks and cron job configurations</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.cronjobs.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Scheduled Tasks</h3>
            <button type="button" @click="addTask" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Add Task</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Task Name</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Schedule</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Status</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Last Run</th>
                  <th class="text-right py-3 px-4 text-sm font-medium text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(task, index) in form.tasks" :key="index" class="border-b border-gray-700/50">
                  <td class="py-3 px-4 text-white">{{ task.task_name }}</td>
                  <td class="py-3 px-4 text-gray-400 text-sm font-mono">{{ task.schedule }}</td>
                  <td class="py-3 px-4">
                    <span class="px-2 py-1 text-xs rounded-full" :class="task.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'">
                      {{ task.status === 'active' ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-gray-400 text-sm">{{ task.last_run || 'Never' }}</td>
                  <td class="py-3 px-4 text-right space-x-2">
                    <button type="button" @click="editTask(index)" class="text-indigo-400 hover:text-indigo-300 text-sm">Edit</button>
                    <button type="button" @click="runTask(index)" class="text-green-400 hover:text-green-300 text-sm">Run Now</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="form.tasks.length === 0" class="text-gray-500 text-sm text-center py-4">No tasks defined</p>
          </div>
        </div>

        <div v-if="editingIndex !== null" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Edit Task</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Task Name</label>
                <input v-model="editingTask.task_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Command</label>
                <input v-model="editingTask.command" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <input v-model="editingTask.description" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Schedule</label>
              <div class="flex gap-4 mt-2 flex-wrap">
                <div v-for="s in schedulePresets" :key="s.value" class="flex items-center gap-2">
                  <input v-model="editingTask.schedule_preset" :value="s.value" type="radio" :id="'task_schedule_' + s.value" @change="onSchedulePreset" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'task_schedule_' + s.value" class="text-gray-300">{{ s.label }}</label>
                </div>
                <div class="flex items-center gap-2">
                  <input v-model="editingTask.schedule_preset" value="custom" type="radio" id="task_schedule_custom" @change="onSchedulePreset" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label for="task_schedule_custom" class="text-gray-300">Custom</label>
                </div>
              </div>
              <input v-if="editingTask.schedule_preset === 'custom'" v-model="editingTask.schedule" type="text" placeholder="*/5 * * * *" class="w-full mt-2 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timeout (seconds)</label>
                <input v-model="editingTask.timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <div class="flex gap-4 mt-2">
                  <div class="flex items-center gap-2">
                    <input v-model="editingTask.status" value="active" type="radio" id="task_status_active" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <label for="task_status_active" class="text-gray-300">Active</label>
                  </div>
                  <div class="flex items-center gap-2">
                    <input v-model="editingTask.status" value="inactive" type="radio" id="task_status_inactive" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <label for="task_status_inactive" class="text-gray-300">Inactive</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex gap-6">
              <div class="flex items-center gap-3">
                <input v-model="editingTask.notify_on_failure" type="checkbox" id="notify_on_failure" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="notify_on_failure" class="text-gray-300">Notify on Failure</label>
              </div>
              <div class="flex items-center gap-3">
                <input v-model="editingTask.notify_on_success" type="checkbox" id="notify_on_success" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="notify_on_success" class="text-gray-300">Notify on Success</label>
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="saveTask" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">Save Task</button>
              <button type="button" @click="cancelEdit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="button" @click="deleteTask" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition ml-auto">Delete Task</button>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

const props = defineProps({ settings: Object })

const schedulePresets = [
  { value: 'every_minute', label: 'Every Minute', cron: '* * * * *' },
  { value: 'every_hour', label: 'Every Hour', cron: '0 * * * *' },
  { value: 'every_6h', label: 'Every 6 Hours', cron: '0 */6 * * *' },
  { value: 'every_12h', label: 'Every 12 Hours', cron: '0 */12 * * *' },
  { value: 'every_24h', label: 'Every 24 Hours', cron: '0 0 * * *' },
]

const form = useForm({
  tasks: props.settings.tasks || [],
})

const editingIndex = ref(null)
const editingTask = ref({})

function addTask() {
  const newTask = {
    task_name: '',
    description: '',
    command: '',
    schedule: '0 * * * *',
    schedule_preset: 'every_hour',
    timeout: 300,
    status: 'active',
    notify_on_failure: true,
    notify_on_success: false,
    last_run: null,
  }
  form.tasks.push(newTask)
  editingIndex.value = form.tasks.length - 1
  editingTask.value = { ...form.tasks[editingIndex.value] }
}

function editTask(index) {
  editingIndex.value = index
  editingTask.value = { ...form.tasks[index] }
  const preset = schedulePresets.find(p => p.cron === editingTask.value.schedule)
  editingTask.value.schedule_preset = preset ? preset.value : 'custom'
}

function saveTask() {
  const preset = schedulePresets.find(p => p.value === editingTask.value.schedule_preset)
  if (preset) {
    editingTask.value.schedule = preset.cron
  }
  form.tasks[editingIndex.value] = { ...editingTask.value }
  editingIndex.value = null
  editingTask.value = {}
}

function cancelEdit() {
  editingIndex.value = null
  editingTask.value = {}
}

function deleteTask() {
  form.tasks.splice(editingIndex.value, 1)
  editingIndex.value = null
  editingTask.value = {}
}

function onSchedulePreset() {
  const preset = schedulePresets.find(p => p.value === editingTask.value.schedule_preset)
  if (preset) {
    editingTask.value.schedule = preset.cron
  }
}

function runTask(index) {
  const task = form.tasks[index]
  if (confirm(`Run task "${task.task_name}" now?`)) {
    router.post(route('admin.settings.cronjobs.run'), { task_name: task.task_name })
  }
}
</script>
