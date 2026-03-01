<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, web } from '@/lib/axios'
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue'

const router = useRouter()
const countries = ref([])
const strengthMeter = ref(null)
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
const fieldErrors = reactive({})

const policy = reactive({
  min_length: 8,
  require_uppercase: true,
  require_number: true,
  require_special: true,
})

onMounted(async () => {
  const [countriesRes] = await Promise.allSettled([
    api.get('/countries'),
    api.get('/password-policy').then(({ data }) => Object.assign(policy, data.data)),
  ])
  if (countriesRes.status === 'fulfilled') countries.value = countriesRes.value.data
})

const onCountryChange = () => {
  const c = countries.value.find((x) => x.code === form.value.country)
  form.value.timezone = c?.timezone || ''
}

const onContactNumberInput = (event) => {
  form.value.phone = String(event?.target?.value ?? '').replace(/\D/g, '').slice(0, 12)
  if (fieldErrors.phone) delete fieldErrors.phone
}

const validatePhone = (value) => {
  if (!/^\d{12}$/.test(value)) return 'Must be exactly 12 digits with no spaces (e.g. 971XXXXXXXXX).'
  if (!value.startsWith('971')) return 'Must start with 971.'
  return null
}

const validatePasswordAgainstPolicy = (value) => {
  const minLength = Number(policy.min_length) || 8
  if (!value || value.length < minLength) return `Password must be at least ${minLength} characters.`
  if (policy.require_uppercase && !/[A-Z]/.test(value)) return 'Password must contain at least one uppercase letter.'
  if (policy.require_number && !/[0-9]/.test(value)) return 'Password must contain at least one number.'
  if (policy.require_special && !/[^A-Za-z0-9]/.test(value)) return 'Password must contain at least one special character.'
  return null
}

const submit = async () => {
  error.value = null
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])

  if (!form.value.name?.trim()) fieldErrors.name = 'Name is required.'
  if (!form.value.email?.trim()) fieldErrors.email = 'Email is required.'
  if (!form.value.phone?.trim()) {
    fieldErrors.phone = 'Contact Number is required.'
  } else {
    const phoneErr = validatePhone(form.value.phone.trim())
    if (phoneErr) fieldErrors.phone = phoneErr
  }
  if (!form.value.country) fieldErrors.country = 'Country is required.'
  if (!form.value.cnic_number?.trim()) fieldErrors.cnic_number = 'CNIC Number is required.'
  const passwordErr = validatePasswordAgainstPolicy(form.value.password || '')
  if (passwordErr) fieldErrors.password = passwordErr
  if (!form.value.password_confirmation) {
    fieldErrors.password_confirmation = 'Confirm Password is required.'
  } else if (form.value.password !== form.value.password_confirmation) {
    fieldErrors.password_confirmation = 'Password and confirmation do not match.'
  }

  if (Object.keys(fieldErrors).length > 0) {
    error.value = 'Please fix the highlighted errors.'
    return
  }

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
          <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.name ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
          <input v-model="form.email" type="email" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.email ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
          <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
          <input
            v-model="form.phone"
            type="text"
            maxlength="12"
            required
            placeholder="971XXXXXXXXX"
            class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            :class="fieldErrors.phone ? 'border-red-400 bg-red-50' : 'border-gray-300'"
            @input="onContactNumberInput"
          />
          <p v-if="fieldErrors.phone" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
          <select v-model="form.country" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.country ? 'border-red-400 bg-red-50' : 'border-gray-300'" @change="onCountryChange">
            <option value="">Select your country</option>
            <option v-for="c in countries" :key="c.id" :value="c.code" :data-timezone="c.timezone">{{ c.name }}</option>
          </select>
          <p v-if="fieldErrors.country" class="mt-1 text-xs text-red-600">{{ fieldErrors.country }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
          <input v-model="form.timezone" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">CNIC Number <span class="text-red-500">*</span></label>
          <input v-model="form.cnic_number" type="text" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.cnic_number ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
          <p v-if="fieldErrors.cnic_number" class="mt-1 text-xs text-red-600">{{ fieldErrors.cnic_number }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
          <input v-model="form.password" type="password" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.password ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
          <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">{{ fieldErrors.password }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
          <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="fieldErrors.password_confirmation ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
          <p v-if="fieldErrors.password_confirmation" class="mt-1 text-xs text-red-600">{{ fieldErrors.password_confirmation }}</p>
        </div>
        <PasswordStrengthMeter ref="strengthMeter" :password="form.password" :policy="policy" />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" :disabled="loading" class="w-full py-2.5 px-4 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 focus:ring-green-500 disabled:opacity-50">
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
