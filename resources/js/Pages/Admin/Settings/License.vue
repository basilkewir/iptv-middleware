<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">License Management</h1>
        <p class="text-gray-400 mt-1">View and manage your IPTV middleware license</p>
      </div>

      <!-- Status banner -->
      <div v-if="license" class="rounded-xl p-5 border flex items-center gap-4"
        :class="license.is_valid ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'">
        <component :is="license.is_valid ? ShieldCheck : ShieldAlert"
          class="w-10 h-10" :class="license.is_valid ? 'text-green-400' : 'text-red-400'" />
        <div class="flex-1">
          <h3 class="font-semibold text-lg" :class="license.is_valid ? 'text-green-300' : 'text-red-300'">
            {{ license.is_valid ? 'License Active' : 'License Invalid — System Locked' }}
          </h3>
          <p class="text-sm" :class="license.is_valid ? 'text-green-400/70' : 'text-red-400/70'">
            {{ license.is_valid ? `Valid ${license.license_type} license for ${license.hotel_name}` : `Status: ${license.status}. The panel will show the License Required page.` }}
          </p>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-medium uppercase tracking-wide"
          :class="license.is_valid ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'">
          {{ license.status }}
        </span>
      </div>
      <div v-else class="rounded-xl p-5 bg-red-500/10 border border-red-500/30 flex items-center gap-4">
        <ShieldAlert class="w-10 h-10 text-red-400" />
        <div>
          <h3 class="font-semibold text-lg text-red-300">No License Found</h3>
          <p class="text-sm text-red-400/70">Activate a license key below to unlock the system.</p>
        </div>
      </div>

      <!-- Activation / change key -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-1">{{ license ? 'Change License Key' : 'Activate License' }}</h3>
        <p class="text-gray-400 text-sm mb-4">Enter the license key provided with your purchase.</p>
        <form @submit.prevent="form.post(route('admin.settings.license.activate'))" class="flex gap-3">
          <input
            v-model="form.license_key"
            type="text"
            required
            placeholder="XXXX-XXXX-XXXX-XXXX"
            class="flex-1 px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 font-mono uppercase tracking-wider"
            :class="{ 'border-red-500': form.errors.license_key }"
          />
          <button type="submit" :disabled="form.processing"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-600/50 text-white font-semibold rounded-lg transition">
            {{ form.processing ? 'Verifying…' : (license ? 'Update Key' : 'Activate') }}
          </button>
        </form>
        <p v-if="form.errors.license_key" class="mt-2 text-sm text-red-400">{{ form.errors.license_key }}</p>
      </div>

      <!-- License details -->
      <div v-if="license" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-4">License Details</h3>
        <dl class="grid grid-cols-2 gap-x-8 gap-y-4">
          <div>
            <dt class="text-gray-400 text-sm">License Key</dt>
            <dd class="text-white font-mono mt-0.5 select-all" :title="license.license_key">{{ maskedKey }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-sm">Hotel</dt>
            <dd class="text-white mt-0.5">{{ license.hotel_name }} <span class="text-gray-500">({{ license.hotel_id }})</span></dd>
          </div>
          <div>
            <dt class="text-gray-400 text-sm">Type</dt>
            <dd class="text-white capitalize mt-0.5">{{ license.license_type }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-sm">Expires</dt>
            <dd class="text-white mt-0.5">{{ license.expires_at ? formatDate(license.expires_at) : 'Never (perpetual)' }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-sm">Devices</dt>
            <dd class="text-white mt-0.5">{{ license.current_devices }} / {{ license.max_devices }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-sm">Last Validated</dt>
            <dd class="text-white mt-0.5">{{ license.last_validated_at ? formatDate(license.last_validated_at) : '—' }} <span class="text-gray-500">({{ license.validation_count }} validations)</span></dd>
          </div>
          <div class="col-span-2">
            <dt class="text-gray-400 text-sm mb-1">Enabled Features</dt>
            <dd class="flex flex-wrap gap-2 mt-0.5">
              <span v-for="f in license.features" :key="f"
                class="px-2.5 py-0.5 bg-indigo-500/15 text-indigo-300 rounded-full text-xs font-medium">
                {{ f.replaceAll('_', ' ') }}
              </span>
              <span v-if="!license.features.length" class="text-gray-500 text-sm">None</span>
            </dd>
          </div>
        </dl>
      </div>

      <!-- Devices -->
      <div v-if="license" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-white">Registered Devices</h3>
          <span class="text-gray-400 text-sm">{{ activeDevices }} of {{ license.max_devices }} slots used</span>
        </div>
        <div v-if="license.devices.length" class="space-y-3">
          <div v-for="device in license.devices" :key="device.id"
            class="flex items-center justify-between bg-gray-750 bg-gray-900/60 rounded-lg px-4 py-3 border border-gray-700/50">
            <div class="flex items-center gap-3">
              <MonitorSmartphone class="w-5 h-5 text-gray-400" />
              <div>
                <p class="text-white text-sm font-medium">{{ device.name || 'Unnamed Device' }}
                  <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="device.status === 'active' ? 'bg-green-500/15 text-green-300' : device.status === 'blocked' ? 'bg-red-500/15 text-red-300' : 'bg-gray-600/40 text-gray-300'">
                    {{ device.status }}
                  </span>
                </p>
                <p class="text-gray-500 text-xs mt-0.5">
                  {{ [device.type, device.model, device.os].filter(Boolean).join(' · ') || 'unknown device' }}
                  <template v-if="device.ip_address"> · IP {{ device.ip_address }}</template>
                  <template v-if="device.last_seen_at"> · last seen {{ formatDate(device.last_seen_at) }}</template>
                </p>
              </div>
            </div>
            <button v-if="device.status !== 'blocked'" @click="revoke(device)"
              class="px-3 py-1.5 text-sm bg-red-600/10 hover:bg-red-600/25 text-red-400 border border-red-600/30 rounded-lg transition">
              Revoke
            </button>
          </div>
        </div>
        <p v-else class="text-gray-500 text-sm">No devices have activated this license yet.</p>
      </div>

      <!-- Danger zone -->
      <div v-if="license && license.is_valid" class="bg-gray-800 rounded-xl p-6 border border-red-900/50">
        <h3 class="text-lg font-semibold text-red-400 mb-1">Danger Zone</h3>
        <p class="text-gray-400 text-sm mb-4">
          Deactivating the license immediately locks the entire system — all pages will require a new valid license key.
        </p>
        <button @click="deactivate"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition">
          Deactivate License
        </button>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ShieldCheck, ShieldAlert, MonitorSmartphone } from 'lucide-vue-next'

const props = defineProps({
  license: Object,
})

const maskedKey = computed(() => props.license?.masked_key ?? '')
const activeDevices = computed(() => props.license?.devices.filter(d => d.status === 'active').length ?? 0)

const form = useForm({
  license_key: '',
})

const revoke = (device) => {
  if (!confirm(`Revoke "${device.name}"? The device will need to re-validate its license.`)) return
  router.delete(route('admin.settings.license.devices.revoke', { device: device.id }), {
    preserveScroll: true,
  })
}

const deactivate = () => {
  if (!confirm('Deactivate the license? The whole system will be locked until a valid key is entered.')) return
  useForm({}).post(route('admin.settings.license.deactivate'))
}

const formatDate = (iso) => {
  try {
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return iso
  }
}
</script>
