<template>
  <div class="min-h-screen bg-gray-950 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">IPTV StreamBox</h1>
      </div>

      <div class="bg-gray-900 rounded-2xl shadow-2xl p-8 border border-gray-800">
        <div class="text-center mb-6">
          <div class="mx-auto w-16 h-16 bg-purple-600/20 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-white mb-2">Verify your email</h2>
          <p class="text-gray-400">
            We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
          </p>
        </div>

        <div v-if="status === 'verification-link-sent'" class="mb-6 p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
          <p class="text-green-400 text-sm text-center">
            A new verification link has been sent to your email address.
          </p>
        </div>

        <div class="space-y-4">
          <button
            @click="resendVerification"
            :disabled="form.processing"
            class="w-full py-3 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-600/50 text-white font-semibold rounded-lg transition-all duration-200 flex items-center justify-center"
          >
            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ form.processing ? 'Sending...' : 'Resend verification email' }}
          </button>

          <div class="text-center">
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="text-sm text-gray-400 hover:text-white transition-colors"
            >
              Log out and use a different account
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

defineProps({
  status: String,
})

const form = useForm()

const resendVerification = () => {
  form.post(route('verification.send'))
}
</script>
