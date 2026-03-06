<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
const rolesDropdownOpen = ref(false)
const rolesDropdownRef = ref(null)

function formatRoleName(name) {
  if (!name) return ''
  return name
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

const assignableRoles = computed(() => {
  const list = (roles.value ?? []).filter((r) => (r?.name ?? '').toLowerCase() !== 'superadmin')
  const seen = new Map()
  for (const r of list) {
    if (!r?.id) continue
    const key = (r.name ?? '').toLowerCase().replace(/[\s_-]+/g, '')
    if (!seen.has(key)) seen.set(key, r)
  }
  return Array.from(seen.values())
})

const selectedRolesLabel = computed(() => {
  const ids = form.value.roles
  if (!ids.length) return 'Select roles to assign'
  return assignableRoles.value
    .filter((r) => ids.includes(r.id))
    .map((r) => formatRoleName(r.name))
    .join(', ')
})

function toggleRole(roleId) {
  const ids = form.value.roles
  const idx = ids.indexOf(roleId)
  if (idx >= 0) ids.splice(idx, 1)
  else ids.push(roleId)
}

function onClickOutside(e) {
  if (rolesDropdownRef.value && !rolesDropdownRef.value.contains(e.target)) {
    rolesDropdownOpen.value = false
  }
}

onMounted(async () => {
  document.addEventListener('mousedown', onClickOutside)
  try {
    const { data } = await usersApi.index({ per_page: 1, page: 1 })
    roles.value = data.roles ?? []
  } catch {
    roles.value = []
  }
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
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
    <router-link to="/users" class="text-sm text-brand-primary hover:text-brand-primary-hover">← Back to Users</router-link>

    <div>
      <h1 class="text-2xl font-bold text-gray-900">Add New User</h1>
      <p class="mt-1 text-sm text-gray-500">Create a new user account and assign roles.</p>
    </div>
    <form autocomplete="off" @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4 max-w-2xl">
      <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">{{ error }}</div>
      <div v-if="successMessage" class="rounded-lg bg-brand-primary-light border border-brand-primary-muted p-4 text-sm text-brand-primary-hover">{{ successMessage }}</div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="Full name" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input v-model="form.email" type="email" name="new_user_email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" required class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="email@example.com" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
          <input v-model="form.password" type="password" name="new_user_password" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" required class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="Enter password" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
          <input v-model="form.password_confirmation" type="password" name="new_user_password_confirmation" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" required class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="Confirm password" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input v-model="form.phone" type="text" class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="+971..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
          <input v-model="form.country" type="text" class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary" placeholder="Country" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Initial Status</label>
          <select v-model="form.status" class="w-full rounded-lg border-gray-300 focus:border-brand-primary focus:ring-brand-primary">
            <option value="pending">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Roles</label>
        <div class="border rounded-xl p-3 bg-gray-50 space-y-2">
          <label v-for="r in assignableRoles" :key="r.id" class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
            <input v-model="form.roles" type="checkbox" :value="r.id" class="rounded border-gray-300" />
            <span>{{ formatRoleName(r.name) }}</span>
          </label>
          <p v-if="!assignableRoles.length" class="text-sm text-gray-500">No roles available.</p>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-4">
        <router-link to="/users" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="px-5 py-2 rounded-lg bg-brand-primary text-white text-sm font-medium hover:bg-brand-primary-hover disabled:opacity-50">
          {{ loading ? 'Creating...' : 'Create User' }}
        </button>
      </div>
    </form>
  </div>
</template>
