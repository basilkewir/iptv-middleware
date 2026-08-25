<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">System Settings</h1>
        <p class="text-gray-400 mt-1">Configure all aspects of your IPTV platform</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link v-for="section in sections" :key="section.key" :href="route('admin.settings.' + section.key)"
          class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-indigo-500 transition group">
          <div class="flex items-center gap-3 mb-2">
            <component :is="section.icon" class="w-5 h-5 text-indigo-400 group-hover:text-indigo-300" />
            <h3 class="text-white font-semibold">{{ section.label }}</h3>
          </div>
          <p class="text-gray-400 text-sm">{{ section.description }}</p>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Settings, Globe, Tv, Film, Rss, Users, Shield, KeyRound, CreditCard,
  FileText, Server, Database, Zap, Mail, Bell, ScrollText, Activity,
  HardDrive, Code, Puzzle, Variable, Clock, Globe2, BadgeCheck
} from 'lucide-vue-next'

const sections = [
  { key: 'license', label: 'License', icon: BadgeCheck, description: 'License status, activation, registered devices, and deactivation' },
  { key: 'general', label: 'General', icon: Settings, description: 'Site name, URL, language, timezone, branding, and maintenance mode' },
  { key: 'localization', label: 'Localization', icon: Globe, description: 'Languages, translations, regional formats, and measurement systems' },
  { key: 'channels', label: 'Channel Settings', icon: Tv, description: 'Channel management, stream types, fallback, and health checks' },
  { key: 'vod', label: 'VOD Settings', icon: Film, description: 'Video on demand, metadata, series, and transcoding profiles' },
  { key: 'epg', label: 'EPG Settings', icon: Rss, description: 'Electronic Program Guide sources, mapping, and output format' },
  { key: 'users', label: 'User Management', icon: Users, description: 'Registration, password policy, and session management' },
  { key: 'security', label: 'Security', icon: Shield, description: '2FA, access control, rate limiting, and encryption' },
  { key: 'roles', label: 'Roles & Permissions', icon: KeyRound, description: 'User roles, permission matrix, and access levels' },
  { key: 'payments', label: 'Payment Gateways', icon: CreditCard, description: 'Stripe, PayPal, and other payment provider configuration' },
  { key: 'billing', label: 'Billing & Invoice', icon: FileText, description: 'Invoice templates, tax settings, and payment reminders' },
  { key: 'server', label: 'Server Config', icon: Server, description: 'Server settings, load balancing, and auto-scaling' },
  { key: 'cache', label: 'Cache', icon: Database, description: 'Redis configuration, cache policies, and warming' },
  { key: 'performance', label: 'Performance', icon: Zap, description: 'Query cache, compression, and resource limits' },
  { key: 'email', label: 'Email', icon: Mail, description: 'SMTP configuration, templates, and queue settings' },
  { key: 'notifications', label: 'Notifications', icon: Bell, description: 'Email, SMS, push, and webhook notification channels' },
  { key: 'logging', label: 'Logging', icon: ScrollText, description: 'Log levels, rotation, categories, and retention' },
  { key: 'monitoring', label: 'Monitoring', icon: Activity, description: 'System metrics, alerts, and health checks' },
  { key: 'backup', label: 'Backup & Restore', icon: HardDrive, description: 'Backup schedules, storage, and restore options' },
  { key: 'api', label: 'API', icon: Code, description: 'API authentication, rate limits, and endpoint access' },
  { key: 'integrations', label: 'Integrations', icon: Puzzle, description: 'Analytics, social media, and community webhooks' },
  { key: 'variables', label: 'System Variables', icon: Variable, description: 'Environment variables, constants, and feature flags' },
  { key: 'cronjobs', label: 'Cron Jobs', icon: Clock, description: 'Scheduled tasks, commands, and execution settings' },
  { key: 'domains', label: 'Domains', icon: Globe2, description: 'Domain management, SSL certificates, and HSTS' },
]
</script>
