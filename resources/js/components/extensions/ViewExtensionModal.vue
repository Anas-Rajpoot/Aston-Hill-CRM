<script setup>
/**
 * View Extension modal – readonly extension details, shown as popup from listing.
 */
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import extensionsApi from '@/services/extensionsApi'
import { toDdMonYyyy } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  extensionId: { type: [Number, String], default: null },
  gateways: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'edit'])

const auth = useAuthStore()
const extension = ref(null)
const loading = ref(false)
const loadError = ref(null)

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.edit'))

watch(
  () => [props.visible, props.extensionId],
  ([visible, id]) => {
    if (visible && id) {
      load()
    } else {
      extension.value = null
      loadError.value = null
    }
  },
  { immediate: true }
)

function statusLabel(status) {
  if (status === 'active') return 'Active'
  if (status === 'inactive') return 'InActive'
  if (status === 'not_created') return 'Not Created'
  return status ?? '—'
}

function statusClass(status) {
  if (status === 'active') return 'bg-emerald-100 text-emerald-800'
  if (status === 'inactive') return 'bg-gray-100 text-gray-700'
  if (status === 'not_created') return 'bg-amber-100 text-amber-800'
  return 'bg-gray-100 text-gray-700'
}

function usageLabel(usage) {
  if (usage === 'assigned') return 'Assigned'
  if (usage === 'unassigned') return 'UnAssigned'
  return usage ?? '—'
}

function usageClass(usage) {
  if (usage === 'assigned') return 'bg-teal-100 text-teal-800'
  return 'bg-gray-100 text-gray-600'
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
  if (str.length >= 10 && str.includes('-')) return toDdMonYyyy(str.slice(0, 10)) || '—'
  return str
})

async function load() {
  const id = props.extensionId
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
  emit('close')
}

function openEditModal() {
  if (canEdit.value) {
    emit('close')
    emit('edit')
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="view-extension-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-lg max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="view-extension-title" class="text-lg font-semibold text-gray-900">Extension Details</h2>
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

          <div class="flex-1 min-h-0 overflow-y-auto">
            <!-- Loading -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-teal-600 mb-3" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="text-sm text-gray-500">Loading extension…</p>
            </div>

            <!-- Error -->
            <div v-else-if="loadError || !extension" class="px-6 py-5">
              <p class="text-red-700">{{ loadError || 'Extension not found.' }}</p>
            </div>

            <template v-else>
              <div class="px-6 py-5 space-y-6">
                <!-- Primary Information -->
                <section>
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">Primary Information</h3>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mt-3">
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Extension</dt>
                      <dd class="mt-0.5 text-base font-medium text-green-600">{{ extension.extension || '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gateway</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ extension.gateway || '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Landline Number</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ extension.landline_number || '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Username</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ extension.username || '—' }}</dd>
                    </div>
                  </dl>
                </section>

                <!-- Credentials -->
                <section class="pt-4 border-t border-gray-100">
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">Credentials</h3>
                  <dl class="mt-3">
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Password</dt>
                      <dd class="mt-0.5 text-sm font-mono text-gray-600">{{ extension.password ? '••••••••' : '—' }}</dd>
                    </div>
                  </dl>
                </section>

                <!-- Status Information -->
                <section class="pt-4 border-t border-gray-100">
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">Status Information</h3>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mt-3">
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Status</dt>
                      <dd>
                        <span
                          :class="statusClass(extension.status)"
                          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                          {{ statusLabel(extension.status) }}
                        </span>
                      </dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Usage</dt>
                      <dd>
                        <span
                          :class="usageClass(extension.usage || (extension.assigned_to ? 'assigned' : 'unassigned'))"
                          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                          {{ usageLabel(extension.usage || (extension.assigned_to ? 'assigned' : 'unassigned')) }}
                        </span>
                      </dd>
                    </div>
                  </dl>
                </section>

                <!-- Assignment Information -->
                <section class="pt-4 border-t border-gray-100">
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">Assignment Information</h3>
                  <dl class="mt-3">
                    <div>
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Assigned To</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ extension.assigned_to_name || 'Not Assigned' }}</dd>
                    </div>
                  </dl>
                </section>

                <!-- Comment -->
                <section class="pt-4 border-t border-gray-100">
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">Comment</h3>
                  <div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2.5 text-sm text-gray-700 min-h-[2.5rem]">
                    {{ extension.comment || '—' }}
                  </div>
                </section>

                <!-- System Information -->
                <section class="pt-4 border-t border-gray-100">
                  <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-200">System Information</h3>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mt-3">
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

              <div class="flex flex-shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4 bg-gray-50">
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
                  class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                  @click="openEditModal"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  Edit Extension
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
