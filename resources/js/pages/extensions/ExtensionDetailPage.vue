<script setup>
/**
 * Extension Details page – design per image: sections (Primary Information, Credentials, Status, Assignment, Comment, System Information), Close + Edit Extension.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import extensionsApi from '@/services/extensionsApi'
import { toDdMonYyyy } from '@/lib/dateFormat'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import EditExtensionModal from '@/components/extensions/EditExtensionModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const extension = ref(null)
const loading = ref(true)
const loadError = ref(null)
const editModalVisible = ref(false)

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.edit'))

function statusLabel(status) {
  if (status === 'active') return 'Active'
  if (status === 'inactive') return 'InActive'
  if (status === 'not_created') return 'Not Created'
  return status ?? '—'
}

function usageLabel(usage) {
  if (usage === 'assigned') return 'Assigned'
  if (usage === 'unassigned') return 'UnAssigned'
  return usage ?? '—'
}

const createdDateDisplay = computed(() => {
  const d = extension.value?.created_at
  if (!d) return '—'
  return toDdMonYyyy(typeof d === 'string' ? d : '') || '—'
})

const lastUpdatedDisplay = computed(() => {
  const d = extension.value?.updated_at_raw ?? extension.value?.updated_at
  if (!d) return '—'
  const str = typeof d === 'string' ? d : ''
  if (str.length === 10 && str.includes('-')) return toDdMonYyyy(str) || '—'
  return str
})

async function load() {
  const id = route.params.id
  if (!id) return
  loading.value = true
  loadError.value = null
  try {
    const { data } = await extensionsApi.show(id)
    extension.value = data.data ?? null
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load extension.'
    extension.value = null
  } finally {
    loading.value = false
  }
}

function close() {
  router.push('/cisco-extensions')
}

function openEditModal() {
  if (canEdit.value) editModalVisible.value = true
}

function onEditUpdated() {
  editModalVisible.value = false
  load()
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-3xl">
      <div class="mb-4">
        <Breadcrumbs />
      </div>

      <div
        v-if="loading"
        class="flex items-center justify-center py-16"
      >
        <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div
        v-else-if="loadError || !extension"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ loadError || 'Extension not found.' }}
        <button
          type="button"
          class="ml-2 font-medium underline"
          @click="close"
        >
          Back to list
        </button>
      </div>

      <div
        v-else
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
      >
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h1 class="text-xl font-semibold text-gray-900">Extension Details</h1>
          <button
            type="button"
            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            aria-label="Close"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="px-6 py-5 space-y-6">
          <!-- Primary Information -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Primary Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Extension</dt>
                <dd class="mt-0.5 text-base font-medium text-teal-600">{{ extension.extension || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Landline Number</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ extension.landline_number || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gateway</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ extension.gateway || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Username</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ extension.username || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Credentials -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Credentials</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Password</dt>
                <dd class="mt-0.5 font-mono text-sm text-gray-600">{{ extension.password ? '........' : '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Status Information -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Status Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</dt>
                <dd class="mt-0.5">
                  <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                    {{ statusLabel(extension.status) }}
                  </span>
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Usage</dt>
                <dd class="mt-0.5">
                  <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                    {{ usageLabel(extension.usage) }}
                  </span>
                </dd>
              </div>
            </dl>
          </section>

          <!-- Assignment Information -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Assignment Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Assigned To</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ extension.assigned_to_name || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Comment -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Comment</h2>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-700">
              {{ extension.comment || '—' }}
            </div>
          </section>

          <!-- System Information -->
          <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">System Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Created Date</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ createdDateDisplay }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Updated</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ lastUpdatedDisplay }}</dd>
              </div>
            </dl>
          </section>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
          <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="close"
          >
            Close
          </button>
          <button
            v-if="canEdit"
            type="button"
            class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
            @click="openEditModal"
          >
            Edit Extension
          </button>
        </div>
      </div>
    </div>

    <EditExtensionModal
      :visible="editModalVisible"
      :extension-id="extension?.id"
      :gateways="[]"
      :statuses="[]"
      @close="editModalVisible = false"
      @updated="onEditUpdated"
    />
  </div>
</template>
