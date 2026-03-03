<script setup>
import { ref } from 'vue'
import { ensureCsrfCookie, web } from '@/lib/axios'

const email = ref('')
const error = ref(null)
const success = ref(null)
const loading = ref(false)

const submit = async () => {
  error.value = null
  success.value = null
  loading.value = true
  try {
    await ensureCsrfCookie()
    await web.post('/forgot-password', { email: email.value })
    success.value = 'Password reset link has been sent to your email.'
  } catch (e) {
    const err = e?.response?.data
    error.value = err?.errors?.email?.[0] || err?.message || 'Failed to send reset link'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <h1 class="text-xl font-semibold text-gray-800 mb-2">Forgot password</h1>
      <p class="text-sm text-gray-600 mb-6">
        Enter your email and we'll send you a password reset link.
      </p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="email" type="email" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="you@example.com" />
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="success" class="text-sm text-green-600">{{ success }}</p>
        <button type="submit" :disabled="loading" class="w-full py-2.5 px-4 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 focus:ring-green-500 disabled:opacity-50">
          {{ loading ? 'Sending...' : 'Email Password Reset Link' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        <router-link to="/login" class="text-indigo-600 hover:underline">Back to login</router-link>
      </p>
    </div>
  </div>
</template>
