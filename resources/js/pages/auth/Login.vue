<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref(null)
const loading = ref(false)
const sessionMessage = ref(null)

onMounted(() => {
  try {
    const msg = sessionStorage.getItem('session_terminated_msg')
    if (msg) {
      sessionMessage.value = msg
      sessionStorage.removeItem('session_terminated_msg')
    }
  } catch { /* */ }
})

const submit = async () => {
  error.value = null
  loading.value = true
  try {
    const data = await auth.login({
      email: email.value,
      password: password.value,
    })
    const redirect = data?.redirect || '/'
    await nextTick()
    await router.push(redirect)
  } catch (e) {
    const err = e?.response?.data
    error.value = err?.errors?.email?.[0] || err?.message || 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <!-- Session terminated banner -->
      <div v-if="sessionMessage" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        <p class="text-sm text-amber-700">{{ sessionMessage }}</p>
      </div>

      <h1 class="text-xl font-semibold text-gray-800 mb-2">Sign in</h1>
      <p class="text-sm text-gray-600 mb-6">Enter your credentials to access the dashboard.</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="you@example.com"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2.5 px-4 rounded-lg bg-brand-primary text-white font-medium hover:bg-brand-primary-hover focus:ring-brand-primary disabled:opacity-50"
        >
          {{ loading ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>

      <div class="mt-4 space-y-1 text-center text-sm">
        <p>
          <router-link to="/forgot-password" class="text-indigo-600 hover:underline">Forgot password?</router-link>
        </p>
        <p class="text-gray-600">
          Don't have an account?
          <router-link to="/register" class="text-indigo-600 hover:underline">Register</router-link>
        </p>
      </div>
    </div>
  </div>
</template>
