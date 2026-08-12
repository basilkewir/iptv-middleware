<template>
  <div class="min-h-screen bg-gray-950 flex">
    <!-- Mobile overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 border-r border-gray-800 flex flex-col transform transition-transform duration-200 ease-in-out',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="p-5 border-b border-gray-800 flex items-center justify-between">
        <Link :href="'/dashboard'" class="flex items-center gap-3">
          <div class="w-9 h-9 bg-purple-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-sm">IPTV</span>
          </div>
          <span class="text-lg font-bold text-white">IPTV Middleware</span>
        </Link>
        <button
          v-if="showMobileClose"
          @click="sidebarOpen = false"
          class="lg:hidden p-1.5 rounded-lg hover:bg-gray-800 transition-colors tv-focusable"
        >
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors tv-focusable"
          :class="isActive(item.href) ? 'bg-purple-600/20 text-purple-400' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
        >
          <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
          {{ item.label }}
        </Link>
      </nav>

      <div class="p-4 border-t border-gray-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
            <span class="text-xs font-medium text-white">{{ userInitials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ userName }}</p>
            <p class="text-xs text-gray-500 truncate">{{ userEmail }}</p>
          </div>
        </div>
      </div>
    </aside>

    <div class="flex-1 lg:pl-64">
      <!-- Header -->
      <div v-if="$slots.header" class="bg-gray-900 border-b border-gray-800 px-4 sm:px-6 lg:px-8 py-3 sm:py-4 flex items-center gap-3">
        <button
          v-if="showMobileMenu"
          @click="sidebarOpen = true"
          class="lg:hidden p-1.5 rounded-lg hover:bg-gray-800 transition-colors tv-focusable"
        >
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <slot name="header" />
      </div>
      <main class="p-4 sm:p-6 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, h, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const sidebarOpen = ref(false)
const showMobileClose = ref(true)
const showMobileMenu = ref(true)

const user = computed(() => page.props?.auth?.user)

const userName = computed(() => {
  if (!user.value) return 'Guest'
  return [user.value.first_name, user.value.last_name].filter(Boolean).join(' ') || user.value.username
})

const userEmail = computed(() => user.value?.email || '')

const userInitials = computed(() => {
  if (!user.value) return '?'
  const first = user.value.first_name?.[0] || user.value.username?.[0] || ''
  const last = user.value.last_name?.[0] || ''
  return (first + last).toUpperCase()
})

const isActive = (href) => {
  const current = page.url
  return current === href || current.startsWith(href + '/')
}

const HomeIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1' })]) } }
const TvIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z' })]) } }
const FilmIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z' })]) } }
const CalendarIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' })]) } }
const CreditCardIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' })]) } }
const BellIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' })]) } }
const UserIcon = { render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' })]) } }

const navItems = computed(() => {
  const items = [
    { href: '/dashboard', label: 'Dashboard', icon: HomeIcon },
    { href: '/channels', label: 'Live TV', icon: TvIcon },
    { href: '/vod', label: 'Movies & Series', icon: FilmIcon },
    { href: '/vod/watchlist', label: 'Watchlist', icon: FilmIcon },
    { href: '/epg', label: 'TV Guide', icon: CalendarIcon },
    { href: '/subscription/plans', label: 'Subscription', icon: CreditCardIcon },
    { href: '/notifications', label: 'Notifications', icon: BellIcon },
    { href: '/my/profile', label: 'Profile', icon: UserIcon },
  ]

  if (user.value?.is_admin) {
    items.push({ href: '/admin/dashboard', label: 'Admin Panel', icon: UserIcon })
  }

  return items
})

const handleKeydown = (e) => {
  if (e.key === 'Escape' && sidebarOpen.value) {
    sidebarOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>
