<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'

const router = useRouter()
const name = ref('')
const description = ref('')
const saving = ref(false)
const errorMessage = ref('')

const save = async () => {
  const n = (name.value || '').trim()
  if (!n) {
    errorMessage.value = 'Role name is required.'
    return
  }
  saving.value = true
  errorMessage.value = ''
  try {
    await api.post('/super-admin/roles', { name: n, description: (description.value || '').trim() || null })
    router.push({ path: '/roles', query: { created: '1' } })
  } catch (e) {
    const msg = e?.response?.data?.errors?.name?.[0] || e?.response?.data?.message || 'Failed to create role.'
    errorMessage.value = msg
  } finally {
    saving.value = false
  }
}

const cancel = () => router.push('/roles')
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Add Role</h1>
      <p class="mt-1 text-sm text-gray-500">Create a new role. You can assign permissions on the next step.</p>
    </div>

    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 max-w-md">
      <div class="space-y-4">
        <div>
          <label for="role-name" class="block text-sm font-medium text-gray-700">Role name</label>
          <input
            id="role-name"
            v-model="name"
            type="text"
            placeholder="e.g. Manager, Sales Agent"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          />
        </div>
        <div>
          <label for="role-description" class="block text-sm font-medium text-gray-700">Description (optional)</label>
          <textarea
            id="role-description"
            v-model="description"
            rows="3"
            placeholder="Brief description of this role's responsibilities"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          />
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="saving"
            @click="save"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {{ saving ? 'Creating…' : 'Create Role' }}
          </button>
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
