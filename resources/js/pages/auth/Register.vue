<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, web } from '@/lib/axios'

const router = useRouter()
const countries = ref([])
const form = ref({
  name: '',
  email: '',
  phone: '',
  country: '',
  timezone: '',
  cnic_number: '',
  password: '',
  password_confirmation: '',
})
const error = ref(null)
const loading = ref(false)

onMounted(async () => {
  const { data } = await api.get('/countries')
  countries.value = data
})

const onCountryChange = () => {
  const c = countries.value.find((x) => x.code === form.value.country)
  form.value.timezone = c?.timezone || ''
}

const submit = async () => {
  error.value = null
  loading.value = true
  try {
    await web.get('/sanctum/csrf-cookie')
    await web.post('/register', form.value)
    router.push('/login')
  } catch (e) {
    const err = e?.response?.data
    error.value = err?.errors ? Object.values(err.errors).flat().join(' ') : err?.message || 'Registration failed'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4 py-8">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
      <h1 class="text-xl font-semibold text-gray-800 mb-2">Create account</h1>
      <p class="text-sm text-gray-600 mb-6">Register and wait for super admin approval.</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="form.email" type="email" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
          <input v-model="form.phone" type="tel" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country *</label>
          <select v-model="form.country" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @change="onCountryChange">
            <option value="">Select your country</option>
            <option v-for="c in countries" :key="c.id" :value="c.code" :data-timezone="c.timezone">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
          <input v-model="form.timezone" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">CNIC Number *</label>
          <input v-model="form.cnic_number" type="text" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
          <input v-model="form.password" type="password" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
          <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" :disabled="loading" class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50">
          {{ loading ? 'Registering...' : 'Register' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        Already have an account?
        <router-link to="/login" class="text-indigo-600 hover:underline">Sign in</router-link>
      </p>
    </div>
  </div>
</template>
