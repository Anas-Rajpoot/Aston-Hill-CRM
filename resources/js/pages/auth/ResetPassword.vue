<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { web } from '@/lib/axios'
import api from '@/lib/axios'
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue'

const route = useRoute()
const router = useRouter()
const token = computed(() => route.params.token)

const form = ref({
  email: '',
  password: '',
  password_confirmation: '',
})
const error = ref(null)
const loading = ref(false)
const strengthMeter = ref(null)

const policy = reactive({
  min_length: 8,
  require_uppercase: true,
  require_number: true,
  require_special: true,
})

onMounted(async () => {
  try {
    const { data } = await api.get('/change-password/policy')
    Object.assign(policy, data.data)
  } catch { /* use defaults */ }
})

const submit = async () => {
  error.value = null
  loading.value = true
  try {
    await web.get('/sanctum/csrf-cookie')
    await web.post('/reset-password', {
      token: token.value,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })
    router.push('/login')
  } catch (e) {
    const err = e?.response?.data
    error.value = err?.errors?.email?.[0] || err?.errors?.password?.[0] || err?.message || 'Failed to reset password'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <h1 class="text-xl font-semibold text-gray-800 mb-2">Reset password</h1>
      <p class="text-sm text-gray-600 mb-6">Enter your email and new password.</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="form.email" type="email" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
          <input v-model="form.password" type="password" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
          <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <PasswordStrengthMeter ref="strengthMeter" :password="form.password" :policy="policy" />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" :disabled="loading" class="w-full py-2.5 px-4 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 focus:ring-green-500 disabled:opacity-50">
          {{ loading ? 'Resetting...' : 'Reset Password' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        <router-link to="/login" class="text-indigo-600 hover:underline">Back to login</router-link>
      </p>
    </div>
  </div>
</template>
