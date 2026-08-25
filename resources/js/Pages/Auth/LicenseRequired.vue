<template>
  <div class="min-h-screen bg-gray-950 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="mx-auto mb-4 w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
          <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-white mb-2">License Required</h1>
        <p class="text-gray-400">This system requires a valid license key to continue.</p>
      </div>

      <div class="bg-gray-900 rounded-2xl shadow-2xl p-8 border border-gray-800">
        <div v-if="$page.props.flash?.status" class="mb-6 p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
          <p class="text-green-400 text-sm">{{ $page.props.flash.status }}</p>
        </div>

        <form @submit.prevent="submit">
          <div v-if="form.errors.license_key" class="mb-6 p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
            <p class="text-red-400 text-sm">{{ form.errors.license_key }}</p>
          </div>

          <div class="mb-5">
            <label for="license-key" class="block text-sm font-medium text-gray-300 mb-2">License Key</label>
            <input
              id="license-key"
              v-model="form.license_key"
              type="text"
              required
              autofocus
              placeholder="XXXX-XXXX-XXXX-XXXX"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all font-mono uppercase tracking-wider"
              :class="{ 'border-red-500': form.errors.license_key }"
            />
            <p class="mt-2 text-xs text-gray-500">
              Enter the license key provided with your IPTV middleware purchase.
            </p>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full py-3 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-600/50 text-white font-semibold rounded-lg transition-all duration-200 flex items-center justify-center"
          >
            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ form.processing ? 'Activating...' : 'Activate License' }}
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-gray-500">
        Don't have a license key?
        <a href="#" class="text-purple-400 hover:text-purple-300 transition-colors">Contact support</a>
      </p>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  license_key: '',
})

const submit = () => {
  form.post('/license/activate')
}
</script>
