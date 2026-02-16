<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const router = useRouter()
const route = useRoute()
const roleId = computed(() => route.params.role)
const name = ref('')
const description = ref('')
const status = ref('active')
const saving = ref(false)
const loading = ref(true)
const errorMessage = ref('')

const load = async () => {
  if (!roleId.value) return
  loading.value = true
  try {
    const { data } = await api.get(`/super-admin/roles/${roleId.value}`)
    const d = data.data
    name.value = d?.name ?? ''
    description.value = d?.description ?? ''
    status.value = d?.status ?? 'active'
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to load role.'
  } finally {
    loading.value = false
  }
}

const save = async () => {
  const n = (name.value || '').trim()
  if (!n) {
    errorMessage.value = 'Role name is required.'
    return
  }
  saving.value = true
  errorMessage.value = ''
  try {
    await api.put(`/super-admin/roles/${roleId.value}`, { name: n, description: (description.value || '').trim() || null, status: status.value })
    router.push({ path: '/roles', query: { updated: '1' } })
  } catch (e) {
    const msg = e?.response?.data?.errors?.name?.[0] || e?.response?.data?.message || 'Failed to update role.'
    errorMessage.value = msg
  } finally {
    saving.value = false
  }
}

const cancel = () => router.push('/roles')

onMounted(load)
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Edit Role</h1>
      <p class="mt-1 text-sm text-gray-500">Change the role name. Use Permissions to control access.</p>
    </div>
    <Breadcrumbs />

    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <div v-if="loading" class="rounded-xl border border-gray-200 bg-white shadow-sm p-8 text-center text-gray-500">
      Loading…
    </div>
    <div v-else class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 max-w-md">
      <div class="space-y-4">
        <div>
          <label for="role-name" class="block text-sm font-medium text-gray-700">Role name</label>
          <input
            id="role-name"
            v-model="name"
            type="text"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          />
        </div>
        <div>
          <label for="role-description" class="block text-sm font-medium text-gray-700">Description (optional)</label>
          <textarea
            id="role-description"
            v-model="description"
            rows="3"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          />
        </div>
        <div>
          <label for="role-status" class="block text-sm font-medium text-gray-700">Status</label>
          <select
            id="role-status"
            v-model="status"
            :disabled="name.toLowerCase() === 'superadmin'"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="saving"
            @click="save"
            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <router-link
            :to="`/roles/${roleId}/permissions`"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Permissions
          </router-link>
          <button
            type="button"
            @click="cancel"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
