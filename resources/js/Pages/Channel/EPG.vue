<template>
  <AppLayout>
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
      <!-- Date Navigation -->
      <div class="flex items-center justify-between mb-4 sm:mb-6">
        <button
          @click="prevDay"
          class="flex items-center gap-1 sm:gap-2 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:bg-gray-800 transition-colors text-xs sm:text-sm tv-touch-target tv-focusable"
        >
          <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Previous
        </button>

        <div class="flex items-center gap-2 sm:gap-4">
          <button
            @click="goToToday"
            class="px-2.5 sm:px-3 py-1 sm:py-1.5 text-xs sm:text-sm font-medium rounded-lg transition-colors tv-focusable"
            :class="isToday ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'"
          >
            Today
          </button>
          <h2 class="text-white font-semibold text-sm sm:text-base">{{ formattedDate }}</h2>
        </div>

        <button
          @click="nextDay"
          class="flex items-center gap-1 sm:gap-2 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:bg-gray-800 transition-colors text-xs sm:text-sm tv-touch-target tv-focusable"
        >
          Next
          <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-1">
        <div v-for="i in 10" :key="i" class="h-12 sm:h-14 bg-gray-900 rounded-lg animate-pulse" />
      </div>

      <!-- EPG Grid -->
      <div v-else class="overflow-x-auto">
        <div class="min-w-[800px] sm:min-w-[1000px] md:min-w-[1200px]">
          <!-- Time Header -->
          <div class="flex border-b border-gray-800 pb-2 mb-2 sticky top-16 bg-gray-950 z-40">
            <div class="w-40 sm:w-48 flex-shrink-0" />
            <div class="flex-1 relative h-8">
              <div class="absolute inset-0 flex">
                <div
                  v-for="hour in timeSlots"
                  :key="hour"
                  class="flex-1 text-center border-l border-gray-800 first:border-l-0"
                >
                  <span class="text-xs text-gray-500 font-mono">{{ formatHour(hour) }}</span>
                </div>
              </div>
              <!-- Current time indicator -->
              <div
                v-if="isToday"
                class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-10"
                :style="{ left: currentTimePosition + '%' }"
              >
                <div class="absolute -top-1 -left-1 w-2 h-2 sm:w-2.5 sm:h-2.5 bg-red-500 rounded-full" />
              </div>
            </div>
          </div>

          <!-- Channel Rows -->
          <div v-if="channels.length === 0" class="text-center py-12 sm:py-16">
            <p class="text-gray-500">No channels with EPG data found.</p>
          </div>
          <div v-else class="space-y-1">
            <div
              v-for="channel in channels"
              :key="channel.id"
              class="flex items-center min-h-[3.5rem]"
            >
              <!-- Channel Label -->
              <Link
                :href="route('channels.show', channel.id)"
                class="w-40 sm:w-48 flex-shrink-0 flex items-center gap-2 sm:gap-3 pr-3 sm:pr-4 hover:bg-gray-900 rounded-lg p-1 sm:p-1.5 transition-colors tv-focusable"
              >
                <div class="w-7 h-7 sm:w-8 sm:h-8 bg-gray-800 rounded flex items-center justify-center flex-shrink-0 overflow-hidden">
                  <img v-if="channel.logo" :src="channel.logo" :alt="channel.name" class="w-full h-full object-contain p-0.5" />
                  <svg v-else class="w-3 h-3 sm:w-4 sm:h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </div>
                <span class="text-xs font-medium text-gray-300 truncate">{{ channel.name }}</span>
              </Link>

              <!-- Programs -->
              <div class="flex-1 relative h-10">
                <div class="absolute inset-0 flex">
                  <div
                    v-for="program in getChannelPrograms(channel.id)"
                    :key="program.id"
                    class="relative h-full border-l border-gray-800 first:border-l-0 cursor-pointer group"
                    :style="{ width: getProgramWidth(program) + '%', left: getProgramOffset(program) + '%' }"
                    @click="openProgramDetail(program)"
                  >
                    <div
                      class="absolute inset-0 px-1 sm:px-2 flex items-center overflow-hidden rounded-sm transition-colors"
                      :class="program.is_current ? 'bg-purple-600/30 border border-purple-500/40' : 'bg-gray-900 hover:bg-gray-800 border border-gray-800 hover:border-gray-700'"
                    >
                      <p class="text-xs font-medium truncate" :class="program.is_current ? 'text-purple-300' : 'text-gray-300'">
                        {{ program.title }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Program Detail Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="selectedProgram"
          class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4"
          @click.self="selectedProgram = null"
        >
          <div class="bg-gray-900 rounded-xl sm:rounded-2xl border border-gray-800 max-w-md w-full p-4 sm:p-6">
            <div class="flex items-start justify-between mb-3 sm:mb-4">
              <div>
                <h3 class="text-lg font-bold text-white mb-1">{{ selectedProgram.title }}</h3>
                <p class="text-sm text-purple-400 font-mono">
                  {{ formatTime(selectedProgram.start_time) }} - {{ formatTime(selectedProgram.end_time) }}
                </p>
              </div>
              <button
                @click="selectedProgram = null"
                class="p-1 rounded-lg hover:bg-gray-800 transition-colors tv-touch-target tv-focusable"
              >
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div v-if="selectedProgram.channel" class="flex items-center gap-2 mb-3 sm:mb-4">
              <div class="w-5 h-5 sm:w-6 sm:h-6 bg-gray-800 rounded flex items-center justify-center overflow-hidden">
                <img v-if="selectedProgram.channel.logo" :src="selectedProgram.channel.logo" class="w-full h-full object-contain p-0.5" />
              </div>
              <span class="text-sm text-gray-400">{{ selectedProgram.channel.name }}</span>
            </div>
            <p v-if="selectedProgram.description" class="text-gray-400 text-sm leading-relaxed mb-3 sm:mb-4">
              {{ selectedProgram.description }}
            </p>
            <div v-if="selectedProgram.is_current" class="flex gap-3">
              <Link
                :href="route('channels.show', selectedProgram.channel_id)"
                class="flex-1 flex items-center justify-center gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors tv-touch-target tv-focusable"
              >
                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Watch Now
              </Link>
            </div>
            <div v-else class="text-xs text-gray-600">
              {{ selectedProgram.is_past ? 'This program has already aired.' : 'This program has not started yet.' }}
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
  programs: { type: Object, default: () => ({}) },
  date: { type: String, default: '' },
})

const loading = ref(false)
const selectedDate = ref(props.date || new Date().toISOString().split('T')[0])
const selectedProgram = ref(null)

const HOURS_START = 6
const HOURS_END = 30
const TOTAL_HOURS = HOURS_END - HOURS_START

const timeSlots = computed(() => {
  const slots = []
  for (let i = HOURS_START; i < HOURS_END; i++) {
    slots.push(i % 24)
  }
  return slots
})

const isToday = computed(() => {
  return selectedDate.value === new Date().toISOString().split('T')[0]
})

const formattedDate = computed(() => {
  const date = new Date(selectedDate.value + 'T12:00:00')
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
})

const currentTimePosition = computed(() => {
  if (!isToday.value) return 0
  const now = new Date()
  const hours = now.getHours() + now.getMinutes() / 60
  const adjustedHour = hours - HOURS_START
  return Math.max(0, Math.min(100, (adjustedHour / TOTAL_HOURS) * 100))
})

const getChannelPrograms = (channelId) => {
  return props.programs[channelId] || []
}

const getProgramWidth = (program) => {
  const start = new Date(program.start_time)
  const end = new Date(program.end_time)
  const dayStart = new Date(selectedDate.value + 'T00:00:00')
  const slotStart = new Date(dayStart.getTime() + HOURS_START * 3600000)
  const slotEnd = new Date(dayStart.getTime() + HOURS_END * 3600000)

  const pStart = Math.max(start.getTime(), slotStart.getTime())
  const pEnd = Math.min(end.getTime(), slotEnd.getTime())
  const duration = pEnd - pStart
  const totalDuration = slotEnd.getTime() - slotStart.getTime()

  return Math.max(0.5, (duration / totalDuration) * 100)
}

const getProgramOffset = (program) => {
  const start = new Date(program.start_time)
  const dayStart = new Date(selectedDate.value + 'T00:00:00')
  const slotStart = new Date(dayStart.getTime() + HOURS_START * 3600000)
  const slotEnd = new Date(dayStart.getTime() + HOURS_END * 3600000)

  const offset = start.getTime() - slotStart.getTime()
  const totalDuration = slotEnd.getTime() - slotStart.getTime()

  return Math.max(0, Math.min(100, (offset / totalDuration) * 100))
}

const prevDay = () => {
  const date = new Date(selectedDate.value + 'T12:00:00')
  date.setDate(date.getDate() - 1)
  selectedDate.value = date.toISOString().split('T')[0]
  fetchEpg()
}

const nextDay = () => {
  const date = new Date(selectedDate.value + 'T12:00:00')
  date.setDate(date.getDate() + 1)
  selectedDate.value = date.toISOString().split('T')[0]
  fetchEpg()
}

const goToToday = () => {
  selectedDate.value = new Date().toISOString().split('T')[0]
  fetchEpg()
}

const fetchEpg = () => {
  loading.value = true
  router.get(route('channels.epg.index'), {
    date: selectedDate.value,
  }, {
    preserveState: true,
    onFinish: () => {
      loading.value = false
    },
  })
}

const openProgramDetail = (program) => {
  selectedProgram.value = program
}

const formatHour = (hour) => {
  const h = hour % 24
  const ampm = h >= 12 ? 'PM' : 'AM'
  const display = h === 0 ? 12 : h > 12 ? h - 12 : h
  return `${display}${ampm}`
}

const formatTime = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

onMounted(() => {
  if (!props.date) {
    selectedDate.value = new Date().toISOString().split('T')[0]
  }
})
</script>
