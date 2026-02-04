<script setup>
/**
 * Edit Submission modal – back office form. Only shown for superadmin / backoffice.
 * Opens from pencil icon on lead submissions listing.
 */
import { ref, watch } from 'vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  leadId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(false)
const saving = ref(false)
const lead = ref(null)
const options = ref({
  executives: [],
  call_verification_options: [],
  pending_from_sales_options: [],
  documents_verification_options: [],
  du_status_options: [],
})

const form = ref({
  executive_id: null,
  status: '',
  call_verification: '',
  pending_from_sales: '',
  documents_verification: '',
  submission_date_from: '',
  back_office_notes: '',
  activity: '',
  back_office_account: '',
  work_order: '',
  du_status: '',
  completion_date: '',
  du_remarks: '',
  additional_note: '',
})

const STATUS_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'pending_for_ata', label: 'Pending for ATA' },
  { value: 'pending_for_finance', label: 'Pending for Finance' },
  { value: 'pending_from_sales', label: 'Pending from Sales' },
  { value: 'unassigned', label: 'UnAssigned' },
]

watch(
  () => [props.visible, props.leadId],
  async ([visible, leadId]) => {
    const id = leadId != null ? Number(leadId) : null
    if (!visible || id == null) {
      lead.value = null
      return
    }
    loading.value = true
    try {
      const [leadRes, optionsRes] = await Promise.all([
        leadSubmissionsApi.getLead(id),
        leadSubmissionsApi.getBackOfficeOptions().catch(() => ({})),
      ])
      const data = leadRes?.data ?? leadRes
      lead.value = data
      options.value = {
        executives: optionsRes.executives ?? [],
        call_verification_options: optionsRes.call_verification_options ?? [],
        pending_from_sales_options: optionsRes.pending_from_sales_options ?? [],
        documents_verification_options: optionsRes.documents_verification_options ?? [],
        du_status_options: optionsRes.du_status_options ?? [],
      }
      form.value = {
        executive_id: data.executive_id ?? null,
        status: data.status ?? '',
        call_verification: data.call_verification ?? '',
        pending_from_sales: data.pending_from_sales ?? '',
        documents_verification: data.documents_verification ?? '',
        submission_date_from: data.submission_date_from ?? '',
        back_office_notes: data.back_office_notes ?? '',
        activity: data.activity ?? '',
        back_office_account: data.back_office_account ?? '',
        work_order: data.work_order ?? '',
        du_status: data.du_status ?? '',
        completion_date: data.completion_date ?? '',
        du_remarks: data.du_remarks ?? '',
        additional_note: data.additional_note ?? '',
      }
    } catch {
      lead.value = null
    } finally {
      loading.value = false
    }
  }
)

function formatDate(d) {
  if (!d) return ''
  const date = new Date(d)
  return date.toISOString().slice(0, 10)
}

function formatFileSize(bytes) {
  if (bytes == null) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

/** Service Category display: category_name or category.name from API (same source as listing/detail). */
function categoryDisplay(lead) {
  if (!lead) return '—'
  const name = lead.category_name ?? lead.category?.name
  return name != null && String(name).trim() !== '' ? name : '—'
}

/** Service Type display: type_name or type.name from API. */
function typeNameDisplay(lead) {
  if (!lead) return '—'
  const name = lead.type_name ?? lead.type?.name
  return name != null && String(name).trim() !== '' ? name : '—'
}

function docDisplayName(doc) {
  return doc.original_name || doc.label || doc.doc_key || 'Document'
}

async function downloadDoc(doc) {
  const id = props.leadId != null ? Number(props.leadId) : null
  if (!id) return
  try {
    const blob = await leadSubmissionsApi.downloadDocument(id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // Could show toast
  }
}

const bulkDownloading = ref(false)
async function bulkDownload() {
  const id = props.leadId != null ? Number(props.leadId) : null
  if (!id) return
  bulkDownloading.value = true
  try {
    const blob = await leadSubmissionsApi.bulkDownloadDocuments(id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `lead-submission-${id}-documents.zip`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // Could show toast
  } finally {
    bulkDownloading.value = false
  }
}

function close() {
  emit('close')
}

async function save() {
  const id = props.leadId != null ? Number(props.leadId) : null
  if (!id) return
  saving.value = true
  try {
    await leadSubmissionsApi.updateBackOffice(id, {
      ...form.value,
      executive_id: form.value.executive_id || null,
    })
    emit('saved')
    close()
  } catch {
    // Could show toast
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-6"
        @click.self="close"
      >
        <div
          class="relative w-full max-w-3xl rounded-lg bg-white shadow-xl"
          @click.stop
        >
          <!-- Header -->
          <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Edit Submission</h2>
            <button
              type="button"
              class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="max-h-[calc(100vh-8rem)] overflow-y-auto px-6 py-4">
            <div v-if="loading" class="flex items-center justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <template v-else-if="lead">
              <!-- Back Office Verification -->
              <div class="mb-4 flex gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div>
                  <p class="font-medium text-blue-800">Back Office Verification</p>
                  <p class="text-sm text-blue-700">Review all information and documents carefully. Make necessary corrections before updating the status.</p>
                </div>
              </div>

              <!-- Read-only core details -->
              <div class="mb-4">
                <h3 class="mb-2 text-sm font-medium text-gray-700">Submission core details</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Account Number *</label>
                    <input type="text" :value="lead.account_number" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Company Name *</label>
                    <input type="text" :value="lead.company_name" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Service Category *</label>
                    <input type="text" :value="categoryDisplay(lead)" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Service Type *</label>
                    <input type="text" :value="typeNameDisplay(lead)" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Product *</label>
                    <input type="text" :value="lead.product" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">MRC (AED) *</label>
                    <input type="text" :value="lead.mrc_aed" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Quantity *</label>
                    <input type="text" :value="lead.quantity" class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600" readonly />
                  </div>
                </div>
              </div>

              <!-- Editable fields -->
              <div class="mb-4">
                <h3 class="mb-2 text-sm font-medium text-gray-700">Additional fields</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Executive Name *</label>
                    <select v-model="form.executive_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option :value="null">Select</option>
                      <option v-for="e in options.executives" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Status *</label>
                    <select v-model="form.status" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Call Verification *</label>
                    <select v-model="form.call_verification" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option value="">Select</option>
                      <option v-for="o in options.call_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Pending from Sales *</label>
                    <select v-model="form.pending_from_sales" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option value="">Select</option>
                      <option v-for="o in options.pending_from_sales_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Submission Date From</label>
                    <input v-model="form.submission_date_from" type="date" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Documents Verification *</label>
                    <select v-model="form.documents_verification" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option value="">Select</option>
                      <option v-for="o in options.documents_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Documents (always visible; grid of cards + Bulk download at bottom) -->
              <div class="mb-4">
                <div v-if="lead.documents && lead.documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <div
                    v-for="doc in lead.documents"
                    :key="doc.id"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-100 p-3"
                  >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">
                      D
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium text-gray-900" :title="docDisplayName(doc)">
                        {{ docDisplayName(doc) }}
                      </p>
                      <p class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                    </div>
                    <button
                      type="button"
                      class="shrink-0 rounded p-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                      title="Download"
                      @click="downloadDoc(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </button>
                  </div>
                </div>
                <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">
                  No documents uploaded.
                </div>
                <div class="mt-3 flex justify-end">
                  <button
                    type="button"
                    class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                    :disabled="bulkDownloading || !(lead.documents && lead.documents.length)"
                    @click="bulkDownload"
                  >
                    {{ bulkDownloading ? 'Preparing...' : 'Bulk download' }}
                  </button>
                </div>
              </div>

              <!-- Back Office Notes -->
              <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500">Back Office Notes</label>
                <textarea v-model="form.back_office_notes" rows="3" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes, corrections needed, or any remarks..."></textarea>
              </div>

              <!-- Activity / DU Status -->
              <div class="mb-4">
                <h3 class="mb-2 text-sm font-medium text-gray-700">Activity / DU Status</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Activity</label>
                    <input v-model="form.activity" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Describe Activity" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Account *</label>
                    <input v-model="form.back_office_account" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter Account" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Work Order</label>
                    <input v-model="form.work_order" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Describe Activity" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Du Status *</label>
                    <select v-model="form.du_status" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                      <option value="">Select</option>
                      <option v-for="o in options.du_status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Completion Date</label>
                    <input v-model="form.completion_date" type="date" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  </div>
                </div>
                <div class="mt-2">
                  <label class="block text-xs font-medium text-gray-500">Du Remarks</label>
                  <textarea v-model="form.du_remarks" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes..."></textarea>
                </div>
              </div>

              <!-- Additional Note -->
              <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500">Additional Note</label>
                <textarea v-model="form.additional_note" rows="3" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes, corrections needed, or any remarks...."></textarea>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="close">Cancel</button>
            <button type="button" class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" :disabled="saving || !lead" @click="save">
              <svg v-if="saving" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
              </svg>
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
