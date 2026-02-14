<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const roles = ref([])
const mappings = ref({
  manager: null,
  team_leader: null,
  sales_agent: null,
})
const slotLabels = ref({
  manager: 'Top-level (e.g. Manager, Department Head)',
  team_leader: 'Middle (e.g. Team Leader, Supervisor)',
  sales_agent: 'Bottom (e.g. Sales Agent, Representative)',
})

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/super-admin/team-role-mappings')
    roles.value = data.roles || []
    if (data.mappings) {
      mappings.value = {
        manager: data.mappings.manager?.role_id ?? null,
        team_leader: data.mappings.team_leader?.role_id ?? null,
        sales_agent: data.mappings.sales_agent?.role_id ?? null,
      }
    }
    if (data.slot_labels) {
      slotLabels.value = { ...slotLabels.value, ...data.slot_labels }
    }
  } catch (e) {
    errorMessage.value = e?.response?.status === 403 ? 'Access denied. Super admin only.' : 'Failed to load team hierarchy.'
  } finally {
    loading.value = false
  }
})

const save = async () => {
  saving.value = true
  successMessage.value = ''
  errorMessage.value = ''
  try {
    await api.put('/super-admin/team-role-mappings', mappings.value)
    successMessage.value = 'Team hierarchy roles updated. Forms will use these roles dynamically.'
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to save.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">Team Hierarchy Roles</h1>
      <p class="mt-1 text-sm text-gray-500">
        Assign Spatie roles to the three hierarchy slots. Super admin can change role names or reassign roles – forms will adapt automatically.
      </p>
    </div>
    <Breadcrumbs />

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <form v-else @submit.prevent="save" class="space-y-4">
      <div v-if="errorMessage" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
        {{ errorMessage }}
      </div>
      <div v-if="successMessage" class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
        {{ successMessage }}
      </div>

      <div
        v-for="(slotKey, label) in { manager: 'Manager', team_leader: 'Team Leader', sales_agent: 'Sales Agent' }"
        :key="slotKey"
        class="rounded-lg border border-gray-200 bg-white p-4"
      >
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ label }} Role</label>
        <p class="text-xs text-gray-500 mb-2">{{ slotLabels[slotKey] }}</p>
        <select
          v-model="mappings[slotKey]"
          class="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
        >
          <option :value="null">-- Select Role --</option>
          <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="saving"
          class="px-4 py-2 rounded-lg bg-green-500 text-white text-sm font-medium hover:bg-green-600 disabled:opacity-50"
        >
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </form>
  </div>
</template>
