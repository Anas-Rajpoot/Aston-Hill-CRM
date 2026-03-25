<script setup>
/**
 * Roles & Permissions page: select role, search permissions, configure per module.
 * Progressive rendering: shell renders immediately; modules load with skeleton.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/lib/axios'
import SkeletonPermissionCards from '@/components/skeletons/SkeletonPermissionCards.vue'

const router = useRouter()
const route = useRoute()
const roleId = computed(() => route.params.role)

const role = ref({ id: null, name: '' })
const rolesList = ref([])
const modules = ref([])
const selectedNames = ref(new Set())
const searchQuery = ref('')
const loading = ref(true)
const saving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const MODULE_DISPLAY_ORDER = [
  'submissions',
  'lead-submissions',
  'field-submissions',
  'customer_support_requests',
  'vas_requests',
  'special_requests',
  'accounts',
  'clients',
  'order_status',
  'dsp_tracker',
  'attendance',
  'gsm_verifiers',
  'extensions',
  'expense_tracker',
  'personal_notes',
  'emails_followup',
  'reports',
  'users',
  'teams',
]

const moduleOrderIndex = new Map(MODULE_DISPLAY_ORDER.map((key, idx) => [key, idx]))

const priorityClass = (p) => {
  if (p === 'high') return 'bg-red-100 text-red-800'
  if (p === 'medium') return 'bg-amber-100 text-amber-800'
  return 'bg-brand-primary-light text-brand-primary-hover'
}

/** Filter modules by search; each module keeps full permissions for counts but exposes filtered list for display. */
const filteredModules = computed(() => {
  const q = (searchQuery.value || '').trim().toLowerCase()
  const ordered = [...modules.value].sort((a, b) => {
    const ai = moduleOrderIndex.has(a?.key) ? moduleOrderIndex.get(a?.key) : Number.MAX_SAFE_INTEGER
    const bi = moduleOrderIndex.has(b?.key) ? moduleOrderIndex.get(b?.key) : Number.MAX_SAFE_INTEGER
    return ai - bi
  })
  if (!q) return ordered.map((mod) => ({ ...mod, filteredPermissions: mod.permissions || [] }))
  return ordered
    .map((mod) => {
      const perms = mod.permissions || []
      const filteredPerms = perms.filter(
        (p) =>
          (p.label && p.label.toLowerCase().includes(q)) ||
          (p.name && p.name.toLowerCase().includes(q))
      )
      if (filteredPerms.length === 0) return null
      return { ...mod, filteredPermissions: filteredPerms }
    })
    .filter(Boolean)
})

const onRoleSelect = (e) => {
  const id = e.target.value
  if (id && id !== roleId.value) router.push(`/roles/${id}/permissions`)
}

/** Single API call: structure + role + permission_names + roles_list (one request for page and dropdown). */
const load = async () => {
  if (!roleId.value) return
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get(`/super-admin/roles/${roleId.value}/permissions-page`)
    const d = data.data ?? {}
    modules.value = d.structure ?? []
    role.value = d.role ?? { id: null, name: '' }
    selectedNames.value = new Set(d.permission_names ?? [])
    rolesList.value = d.roles_list?.data ?? []
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to load.'
    rolesList.value = []
  } finally {
    loading.value = false
  }
}

const enabledCount = (module) => {
  const names = (module.permissions || []).map((p) => p.name)
  return names.filter((n) => selectedNames.value.has(n)).length
}

const totalCount = (module) => (module.permissions || []).length

const isModuleAllEnabled = (module) => {
  const total = totalCount(module)
  return total > 0 && enabledCount(module) === total
}

const togglePermission = (name) => {
  const next = new Set(selectedNames.value)
  if (next.has(name)) next.delete(name)
  else next.add(name)
  selectedNames.value = next
}

const setModuleAll = (module, enabled) => {
  const next = new Set(selectedNames.value)
  const names = (module.permissions || []).map((p) => p.name)
  names.forEach((n) => (enabled ? next.add(n) : next.delete(n)))
  selectedNames.value = next
}

const save = async () => {
  saving.value = true
  successMessage.value = ''
  errorMessage.value = ''
  try {
    await api.put(`/super-admin/roles/${roleId.value}/permissions`, {
      permission_names: Array.from(selectedNames.value),
    })
    successMessage.value = 'Permissions updated.'
    setTimeout(() => { successMessage.value = '' }, 3000)
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to save.'
  } finally {
    saving.value = false
  }
}

const isSuperadmin = computed(() => (role.value?.name ?? '').toLowerCase() === 'superadmin')

const goBack = () => router.push('/roles')

onMounted(load)
watch(roleId, load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="goBack"
          class="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 hover:bg-gray-50"
          aria-label="Back to Roles"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
          <p class="mt-1 text-sm text-gray-500">Configure access control and feature permissions for each role.</p>
        </div>
      </div>
      <button
        type="button"
        :disabled="saving || isSuperadmin"
        @click="save"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
        </svg>
        {{ saving ? 'Saving…' : 'Save Changes' }}
      </button>
    </div>
    <div v-if="successMessage" class="rounded-xl bg-brand-primary-light border border-brand-primary-muted px-4 py-3 text-sm text-brand-primary-hover">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>
    <div v-if="isSuperadmin" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
      <strong>Read-only:</strong> Superadmin permissions are managed by the system and cannot be modified.
    </div>

    <!-- Section-level async: skeleton until permissions-page data loads -->
    <SkeletonPermissionCards v-if="loading" :modules="3" :items-per-module="6" />

    <template v-else-if="!loading && modules.length">
      <!-- Control bar: Role dropdown + Search -->
      <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center gap-2">
          <label for="role-select" class="text-sm font-medium text-gray-700">Role:</label>
          <select
            id="role-select"
            :value="roleId"
            @change="onRoleSelect"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
          >
            <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
        <div class="relative flex-1 min-w-[200px] max-w-md">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search permissions..."
            class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
          />
        </div>
      </div>

      <div class="space-y-6">
        <div
          v-for="mod in filteredModules"
          :key="mod.key"
          class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden"
        >
          <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center gap-3">
              <span
                :class="mod.key === 'dashboard' ? 'bg-brand-primary-light text-brand-primary' : 'bg-gray-200 text-gray-600'"
                class="flex h-10 w-10 items-center justify-center rounded-lg"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                </svg>
              </span>
              <div>
                <h2 class="text-base font-semibold text-gray-900">{{ mod.label }}</h2>
                <p class="text-xs text-gray-500">{{ enabledCount(mod) }} of {{ totalCount(mod) }} enabled</p>
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
              <input
                type="checkbox"
                :checked="isModuleAllEnabled(mod)"
                :indeterminate="enabledCount(mod) > 0 && enabledCount(mod) < totalCount(mod)"
                @change="setModuleAll(mod, ($event.target).checked)"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
              Enable All
            </label>
          </div>
          <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
              <label
                v-for="perm in mod.filteredPermissions"
                :key="perm.id"
                class="flex items-center gap-3 cursor-pointer rounded-lg p-2 hover:bg-gray-50"
              >
                <input
                  type="checkbox"
                  :checked="selectedNames.has(perm.name)"
                  @change="togglePermission(perm.name)"
                  class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                />
                <span class="text-sm text-gray-900">{{ perm.label }}</span>
                <span
                  :class="priorityClass(perm.priority)"
                  class="ml-auto rounded px-2 py-0.5 text-xs font-medium capitalize"
                >
                  {{ perm.priority }}
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- No data state (e.g. invalid role) -->
    <div v-else-if="!loading && !modules.length" class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
      No permission data available.
    </div>
  </div>
</template>
