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
      <p class="text-sm text-gray-600 mb-6">
        Enter the 6-digit code from your Google Authenticator app.
      </p>
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">OTP Code</label>
          <input
            v-model="code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="000000"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-lg tracking-widest"
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
    </div>
  </div>
</template>
