<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import usersApi from '@/services/usersApi'

const router = useRouter()
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  country: '',
  roles: [],
  status: 'pending',
})
const roles = ref([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

onMounted(async () => {
  try {
    const { data } = await usersApi.index({ per_page: 1, page: 1 })
    roles.value = data.roles ?? []
  } catch {
    roles.value = []
  }
})

const save = async () => {
  error.value = ''
  successMessage.value = ''
  loading.value = true
  try {
    const payload = { ...form.value }
    if (!payload.password) {
      error.value = 'Password is required.'
      return
    }
    await usersApi.store(payload)
    successMessage.value = 'User created successfully.'
    setTimeout(() => router.push('/users'), 1500)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    error.value = msg || (errs ? Object.values(errs).flat().join(' ') : 'Failed to create user.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <router-link to="/users" class="text-sm text-blue-600 hover:text-blue-700">← Back to Users</router-link>

    <div>
      <h1 class="text-2xl font-bold text-gray-900">Add New User</h1>
      <p class="mt-1 text-sm text-gray-500">Create a new user account and assign roles.</p>
    </div>

    <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4 max-w-2xl">
      <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">{{ error }}</div>
      <div v-if="successMessage" class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">{{ successMessage }}</div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Full name" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="form.email" type="email" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="email@example.com" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
          <input v-model="form.password" type="password" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Min 8 characters" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
          <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Confirm password" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input v-model="form.phone" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="+971..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
          <input v-model="form.country" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Country" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Initial Status</label>
          <select v-model="form.status" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            <option value="pending">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Roles</label>
        <div class="border rounded-xl p-3 bg-gray-50 space-y-2">
          <label v-for="r in roles.filter((x) => x.name !== 'superadmin')" :key="r.id" class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
            <input v-model="form.roles" type="checkbox" :value="r.id" class="rounded border-gray-300" />
            <span>{{ r.name }}</span>
          </label>
          <p v-if="!roles.filter((x) => x.name !== 'superadmin').length" class="text-sm text-gray-500">No roles available.</p>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-4">
        <router-link to="/users" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="px-5 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 disabled:opacity-50">
          {{ loading ? 'Creating...' : 'Create User' }}
        </button>
      </div>
    </form>
  </div>
</template>
