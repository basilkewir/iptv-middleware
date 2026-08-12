<template>
  <div class="min-h-screen bg-gray-950">
    <header class="bg-gray-900/80 backdrop-blur-sm border-b border-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center gap-3">
            <Link :href="route('dashboard')" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Dashboard Widgets</h1>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <p class="text-gray-400 mb-8">Customize which widgets appear on your dashboard and in what order.</p>

      <form @submit.prevent="save">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div
            v-for="widget in widgets"
            :key="widget.id"
            class="bg-gray-900 rounded-xl p-5 border border-gray-800 hover:border-gray-700 transition-all"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="widget.bgClass">
                  <svg class="w-6 h-6" :class="widget.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="widget.icon" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-white font-medium mb-1">{{ widget.name }}</h3>
                  <p class="text-gray-500 text-sm">{{ widget.description }}</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  v-model="widget.enabled"
                  type="checkbox"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
              </label>
            </div>

            <div v-if="widget.enabled" class="mt-4 pt-4 border-t border-gray-800">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-sm text-gray-400">Position</label>
                  <p class="text-xs text-gray-600">Drag to reorder or use arrows</p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="moveWidget(widget.id, -1)"
                    class="w-8 h-8 flex items-center justify-center bg-gray-800 hover:bg-gray-700 rounded-lg text-gray-400 hover:text-white transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    @click="moveWidget(widget.id, 1)"
                    class="w-8 h-8 flex items-center justify-center bg-gray-800 hover:bg-gray-700 rounded-lg text-gray-400 hover:text-white transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="mt-3 space-y-3">
                <div v-if="widget.hasMaxItems">
                  <label class="block text-sm text-gray-400 mb-1">Max items to show</label>
                  <input
                    v-model.number="widget.maxItems"
                    type="number"
                    min="1"
                    max="20"
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <div v-if="widget.hasLayout">
                  <label class="block text-sm text-gray-400 mb-1">Layout</label>
                  <div class="flex gap-2">
                    <button
                      v-for="layout in ['grid', 'list', 'carousel']"
                      :key="layout"
                      type="button"
                      @click="widget.layout = layout"
                      class="px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                      :class="widget.layout === layout
                        ? 'bg-purple-600 text-white'
                        : 'bg-gray-800 text-gray-400 hover:text-white'"
                    >
                      {{ layout.charAt(0).toUpperCase() + layout.slice(1) }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-600/50 text-white font-semibold rounded-lg transition-all duration-200 flex items-center"
          >
            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ form.processing ? 'Saving...' : 'Save Layout' }}
          </button>
          <button
            type="button"
            @click="resetDefaults"
            class="px-6 py-3 bg-gray-800 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors"
          >
            Reset to Defaults
          </button>
        </div>
      </form>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

const props = defineProps({
  savedWidgets: { type: Array, default: () => [] },
})

const form = useForm({
  widgets: [],
})

const defaultWidgets = [
  {
    id: 'subscription',
    name: 'Active Subscription',
    description: 'Shows your current plan and expiry date',
    enabled: true,
    position: 0,
    icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    bgClass: 'bg-purple-500/10',
    iconClass: 'text-purple-400',
  },
  {
    id: 'featured',
    name: 'Featured Content',
    description: 'Horizontal slider of featured movies and shows',
    enabled: true,
    position: 1,
    icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
    bgClass: 'bg-yellow-500/10',
    iconClass: 'text-yellow-400',
    hasMaxItems: true,
    maxItems: 10,
    hasLayout: true,
    layout: 'carousel',
  },
  {
    id: 'recently_watched',
    name: 'Recently Watched',
    description: 'Horizontal scroll of recently viewed content',
    enabled: true,
    position: 2,
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    bgClass: 'bg-blue-500/10',
    iconClass: 'text-blue-400',
    hasMaxItems: true,
    maxItems: 10,
    hasLayout: true,
    layout: 'carousel',
  },
  {
    id: 'continue_watching',
    name: 'Continue Watching',
    description: 'Content with saved progress for quick resume',
    enabled: true,
    position: 3,
    icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z',
    bgClass: 'bg-green-500/10',
    iconClass: 'text-green-400',
    hasMaxItems: true,
    maxItems: 6,
    hasLayout: true,
    layout: 'grid',
  },
  {
    id: 'quick_access',
    name: 'Quick Access',
    description: 'Shortcut cards to channels, VOD, series, and EPG',
    enabled: true,
    position: 4,
    icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    bgClass: 'bg-pink-500/10',
    iconClass: 'text-pink-400',
  },
  {
    id: 'favorites',
    name: 'My Favorites',
    description: 'Quick access to your favorite channels and content',
    enabled: false,
    position: 5,
    icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    bgClass: 'bg-red-500/10',
    iconClass: 'text-red-400',
    hasMaxItems: true,
    maxItems: 8,
  },
  {
    id: 'recommendations',
    name: 'Recommendations',
    description: 'AI-powered content suggestions based on your viewing',
    enabled: false,
    position: 6,
    icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    bgClass: 'bg-indigo-500/10',
    iconClass: 'text-indigo-400',
    hasMaxItems: true,
    maxItems: 12,
    hasLayout: true,
    layout: 'carousel',
  },
]

const widgets = reactive(
  props.savedWidgets.length
    ? [...props.savedWidgets]
    : defaultWidgets.map((w) => ({ ...w }))
)

const moveWidget = (id, direction) => {
  const idx = widgets.findIndex((w) => w.id === id)
  const newIdx = idx + direction
  if (newIdx < 0 || newIdx >= widgets.length) return
  const temp = widgets[idx]
  widgets[idx] = widgets[newIdx]
  widgets[newIdx] = temp
}

const save = () => {
  form.widgets = widgets.map((w, i) => ({
    id: w.id,
    enabled: w.enabled,
    position: i,
    maxItems: w.maxItems,
    layout: w.layout,
  }))
  form.put(route('dashboard.widgets.update'))
}

const resetDefaults = () => {
  widgets.length = 0
  defaultWidgets.forEach((w) => widgets.push({ ...w }))
}
</script>
