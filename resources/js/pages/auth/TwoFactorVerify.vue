<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const code = ref('')
const error = ref(null)
const loading = ref(false)

const submit = async () => {
  error.value = null
  loading.value = true
  try {
    const { data } = await auth.verify2FA(code.value)
    router.push(data?.redirect || '/')
  } catch (e) {
    error.value = e?.response?.data?.errors?.otp?.[0] || 'Invalid OTP. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <h1 class="text-xl font-semibold text-gray-800 mb-2">Two-Factor Verification</h1>
      <div class="text-sm text-gray-600 mb-6 space-y-3">
        <p><strong>Where to get the 6-digit code</strong></p>
        <p>Open <strong>Google Authenticator</strong> (or Microsoft Authenticator, etc.) on your phone, find the entry for this app, and use the 6-digit code shown there. The code changes every 30 seconds.</p>
        <p><strong>Where to enter it</strong></p>
        <p>Type that code in the box below and click Verify.</p>
      </div>
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">6-digit code</label>
          <input
            v-model="code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
            class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-lg tracking-widest"
          />
          <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        </div>
        <button
          type="submit"
          :disabled="loading || code.length !== 6"
          class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Verifying...' : 'Verify' }}
        </button>
      </form>
      <p class="mt-4 text-center text-sm text-gray-500">
        Haven’t set up 2FA yet? <a href="/2fa/setup" class="text-indigo-600 hover:underline">Set up Google Authenticator</a> (log in without 2FA first, then open this link).
      </p>
    </div>
  </div>
</template>
