<template>
  <div class="min-h-screen bg-gray-950 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-lg">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">IPTV StreamBox</h1>
        <p class="text-gray-400">Create your account</p>
      </div>

      <div class="bg-gray-900 rounded-2xl shadow-2xl p-8 border border-gray-800">
        <form @submit.prevent="submit">
          <div v-if="form.errors.general" class="mb-6 p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
            <p class="text-red-400 text-sm">{{ form.errors.general }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-5">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-300 mb-2">First Name</label>
              <input
                id="first_name"
                v-model="form.first_name"
                type="text"
                placeholder="John"
                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                :class="{ 'border-red-500': form.errors.first_name }"
              />
              <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-400">{{ form.errors.first_name }}</p>
            </div>
            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
              <input
                id="last_name"
                v-model="form.last_name"
                type="text"
                placeholder="Doe"
                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                :class="{ 'border-red-500': form.errors.last_name }"
              />
              <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-400">{{ form.errors.last_name }}</p>
            </div>
          </div>

          <div class="mb-5">
            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username</label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              required
              autofocus
              placeholder="johndoe"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
              :class="{ 'border-red-500': form.errors.username }"
            />
            <p v-if="form.errors.username" class="mt-1 text-sm text-red-400">{{ form.errors.username }}</p>
          </div>

          <div class="mb-5">
            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              placeholder="john@example.com"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
              :class="{ 'border-red-500': form.errors.email }"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</p>
          </div>

          <div class="mb-5">
            <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Phone (optional)</label>
            <input
              id="phone"
              v-model="form.phone"
              type="tel"
              placeholder="+1 (555) 123-4567"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
              :class="{ 'border-red-500': form.errors.phone }"
            />
            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-400">{{ form.errors.phone }}</p>
          </div>

          <div class="mb-5">
            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                placeholder="Min 8 characters"
                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all pr-12"
                :class="{ 'border-red-500': form.errors.password }"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
              >
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </button>
            </div>
            <p v-if="form.errors.password" class="mt-1 text-sm text-red-400">{{ form.errors.password }}</p>
          </div>

          <div class="mb-6">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              required
              placeholder="Re-enter password"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
            />
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
            {{ form.processing ? 'Creating account...' : 'Create account' }}
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-gray-400 text-sm">
            Already have an account?
            <Link
              :href="route('login')"
              class="text-purple-400 hover:text-purple-300 font-medium transition-colors"
            >
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

const showPassword = ref(false)

const form = useForm({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  first_name: '',
  last_name: '',
  phone: '',
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>
