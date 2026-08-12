<template>
  <div class="min-h-screen bg-gray-900 text-gray-100">
    <!-- Mobile Navigation Overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 bg-gray-800 border-r border-gray-700 transform transition-transform duration-200 ease-in-out',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex items-center justify-between h-16 px-4 border-b border-gray-700">
        <Link :href="route('dashboard')" class="flex items-center space-x-2">
          <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-sm">IPTV</span>
          </div>
          <span class="text-lg font-semibold">Middleware</span>
        </Link>
        <button
          @click="sidebarOpen = false"
          class="lg:hidden p-1.5 rounded-md hover:bg-gray-700 tv-focusable"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <nav class="mt-4 px-3 space-y-1">
        <Link
          v-for="item in navigation"
          :key="item.name"
          :href="item.href"
          :class="[
            'flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors tv-focusable',
            isActive(item.href)
              ? 'bg-indigo-600/20 text-indigo-400'
              : 'text-gray-400 hover:bg-gray-700/50 hover:text-white'
          ]"
        >
          <component :is="item.icon" class="w-5 h-5 mr-3" />
          {{ item.name }}
        </Link>
      </nav>

      <!-- User Info -->
      <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center">
            <span class="text-sm font-medium">{{ userInitials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ $page.props.auth?.user?.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ $page.props.auth?.user?.email }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="lg:pl-64">
      <!-- Header -->
      <header class="sticky top-0 z-30 h-16 bg-gray-800/80 backdrop-blur-sm border-b border-gray-700">
        <div class="flex items-center justify-between h-full px-4">
          <button
            @click="sidebarOpen = true"
            class="lg:hidden p-2 rounded-md hover:bg-gray-700 tv-focusable"
          >
            <Bars3Icon class="w-5 h-5" />
          </button>

          <div class="flex-1 max-w-xl mx-4">
            <slot name="header-search" />
          </div>

          <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <Link
              :href="route('notifications.index')"
              class="relative p-2 rounded-md hover:bg-gray-700 tv-focusable"
            >
              <BellIcon class="w-5 h-5" />
              <span
                v-if="unreadNotifications > 0"
                class="absolute top-1 right-1 w-4 h-4 bg-red-500 rounded-full text-xs flex items-center justify-center"
              >
                {{ unreadNotifications > 9 ? '9+' : unreadNotifications }}
              </span>
            </Link>

            <!-- Profile Dropdown -->
            <Dropdown align="right" width="48">
              <template #trigger>
                <button class="flex items-center space-x-2 p-1 rounded-md hover:bg-gray-700 tv-focusable">
                  <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center">
                    <span class="text-xs font-medium">{{ userInitials }}</span>
                  </div>
                </button>
              </template>
              <template #content>
                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">Logout</DropdownLink>
              </template>
            </Dropdown>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import {
  Bars3Icon,
  XMarkIcon,
  BellIcon,
  HomeIcon,
  TvIcon,
  FilmIcon,
  CreditCardIcon,
  UserIcon,
  Cog6ToothIcon
} from '@heroicons/vue/24/outline'
import Dropdown from './Dropdown.vue'
import DropdownLink from './DropdownLink.vue'

const page = usePage()
const sidebarOpen = ref(false)

const navigation = computed(() => {
  const items = [
    { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon },
    { name: 'Channels', href: route('channels.index'), icon: TvIcon },
    { name: 'VOD', href: route('vod.index'), icon: FilmIcon },
    { name: 'Subscriptions', href: route('subscriptions.index'), icon: CreditCardIcon },
    { name: 'Profile', href: route('profile.edit'), icon: UserIcon },
    { name: 'Notifications', href: route('notifications.index'), icon: BellIcon },
  ]

  if (page.props.auth?.user?.role === 'admin') {
    items.push({ name: 'Admin', href: route('admin.dashboard'), icon: Cog6ToothIcon })
  }

  return items
})

const userInitials = computed(() => {
  const name = page.props.auth?.user?.name || ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

const unreadNotifications = computed(() => page.props.auth?.user?.unread_notifications || 0)

const isActive = (href) => {
  return window.location.pathname === new URL(href).pathname
}
</script>
