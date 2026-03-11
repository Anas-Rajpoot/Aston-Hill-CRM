<script setup>
/**
 * Expense Details modal – design per image: Expense Information (ID, Status, Product Category,
 * Product Description, Invoice Number, Added By, Expense Date, Created Date), Financial Summary,
 * Attachments (all uploaded images + documents with download). Product Description after Category; Added By after Invoice.
 */
import { ref, watch, computed, onBeforeUnmount } from 'vue'
import { useAuthStore } from '@/stores/auth'
import expensesApi from '@/services/expensesApi'
import api from '@/lib/axios'
import { canModuleAction } from '@/lib/accessControl'

const props = defineProps({
  visible: { type: Boolean, default: false },
  expenseId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close', 'open-edit'])
const auth = useAuthStore()

const expense = ref(null)
const loading = ref(false)
const loadError = ref(null)
const imageBlobUrls = ref({})

const canEdit = computed(() =>
  canModuleAction(auth.user, 'expense-tracker', 'edit', [
    'expense_tracker.edit',
    'expense_tracker.update',
  ])
)

function statusLabel(status) {
  if (status === 'approved') return 'Approved'
  if (status === 'pending') return 'Pending Approval'
  return status ?? '—'
}

function close() {
  emit('close')
}

function goToEdit() {
  if (expense.value?.id && canEdit.value) {
    emit('open-edit', expense.value)
  }
}

/** Build download URL (for links). For img src we use blob URL when available. */
function attachmentUrl(att) {
  return att?.url ?? ''
}

/** Get image src: use blob URL if fetched (for token auth), else direct url (for cookie auth) */
function imageSrc(att) {
  if (!att) return ''
  const id = att.id
  if (imageBlobUrls.value[id]) return imageBlobUrls.value[id]
  return att.url || ''
}

async function loadImageBlobUrls(attachments) {
  const images = (attachments ?? []).filter((a) => a.is_image)
  Object.values(imageBlobUrls.value).forEach((url) => URL.revokeObjectURL(url))
  imageBlobUrls.value = {}
  for (const att of images) {
    if (!att.url) continue
    try {
      const reqUrl = att.url.startsWith('http') ? att.url : (api.defaults.baseURL || '') + (att.url.startsWith('/') ? '' : '/') + att.url
      const { data } = await api.get(reqUrl, { responseType: 'blob' })
      imageBlobUrls.value[att.id] = URL.createObjectURL(data)
    } catch {
      imageBlobUrls.value[att.id] = att.url
    }
  }
}

const imageAttachments = computed(() => (expense.value?.attachments ?? []).filter((a) => a.is_image))
const documentAttachments = computed(() => (expense.value?.attachments ?? []).filter((a) => !a.is_image))

watch(
  () => [props.visible, props.expenseId],
  async ([visible, id]) => {
    if (!visible || !id) {
      Object.values(imageBlobUrls.value).forEach((url) => URL.revokeObjectURL(url))
      imageBlobUrls.value = {}
      expense.value = null
      loadError.value = null
      return
    }
    loading.value = true
    loadError.value = null
    try {
      const { data } = await expensesApi.show(id)
      expense.value = data.data ?? null
      if (expense.value?.attachments?.length) {
        loadImageBlobUrls(expense.value.attachments)
      }
    } catch (e) {
      loadError.value = e?.response?.data?.message || 'Failed to load expense.'
      expense.value = null
    } finally {
      loading.value = false
    }
  }
)

onBeforeUnmount(() => {
  Object.values(imageBlobUrls.value).forEach((url) => URL.revokeObjectURL(url))
})
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
        aria-labelledby="expense-detail-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-3xl max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden border border-gray-200">
          <!-- Header -->
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 id="expense-detail-title" class="text-lg font-bold text-gray-900">Expense Details</h2>
            <button
              type="button"
              class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 min-h-0 overflow-y-auto bg-gray-50/30 px-6 py-5 space-y-5">
            <div v-if="loading" class="flex items-center justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              {{ loadError }}
              <button type="button" class="ml-2 font-medium underline hover:no-underline" @click="close">Close</button>
            </div>

            <template v-else-if="expense">
              <!-- Expense Information – labels muted, values bold for readability -->
              <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="mb-4 text-sm font-bold text-gray-900">Expense Information</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                  <div>
                    <dt class="text-sm text-gray-500">Status</dt>
                    <dd class="mt-0.5">
                      <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="expense.status === 'approved' ? 'bg-brand-primary-light text-brand-primary-hover' : 'bg-amber-100 text-amber-800'"
                      >
                        {{ statusLabel(expense.status) }}
                      </span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Created</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.created_at ?? '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Expense Date</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.expense_date ?? '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Product Category</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.product_category ?? '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Invoice Number</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.invoice_number ?? '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">VAT %</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.vat_percent != null ? expense.vat_percent + '%' : '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Amount Without VAT (AED)</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">
                      {{ expense.amount_without_vat != null ? Number(expense.amount_without_vat).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">VAT Amount (AED)</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">
                      {{ expense.vat_amount_currency != null ? Number(expense.vat_amount_currency).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Total Amount (AED)</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">
                      {{ expense.full_amount != null ? Number(expense.full_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Added By</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900">{{ expense.added_by ?? '—' }}</dd>
                  </div>
                  <div class="sm:col-span-2">
                    <dt class="text-sm text-gray-500">Product Description</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900 leading-snug">{{ expense.product_description ?? '—' }}</dd>
                  </div>
                  <div v-if="expense.comment" class="sm:col-span-2">
                    <dt class="text-sm text-gray-500">Comment / Remarks</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900 leading-snug">{{ expense.comment }}</dd>
                  </div>
                </dl>
              </section>

              <!-- Financial Summary – two-column layout, one row per item; Total Amount bold green -->
              <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="mb-4 text-sm font-bold text-gray-900">Financial Summary</h3>
                <div class="space-y-0">
                  <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600">VAT Percentage</span>
                    <span class="text-sm font-bold text-gray-900">{{ expense.vat_percent != null ? expense.vat_percent + '%' : '—' }}</span>
                  </div>
                  <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600">Amount Without VAT (AED)</span>
                    <span class="text-sm font-bold text-gray-900">
                      {{ expense.amount_without_vat != null ? 'AED ' + Number(expense.amount_without_vat).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600">VAT Amount (AED)</span>
                    <span class="text-sm font-bold text-gray-900">
                      {{ expense.vat_amount_currency != null ? 'AED ' + Number(expense.vat_amount_currency).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                  <div class="border-t border-gray-200 my-2" />
                  <div class="flex items-center justify-between py-2">
                    <span class="text-sm font-bold text-gray-900">Total Amount (AED)</span>
                    <span class="text-base font-bold text-brand-primary">
                      {{ expense.full_amount != null ? 'AED ' + Number(expense.full_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                </div>
              </section>

              <!-- Attachments: images as gallery, documents as cards with download -->
              <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-gray-900">Attachments</h3>
                <div v-if="expense.attachments?.length" class="space-y-4">
                  <!-- Image attachments – show all as thumbnails -->
                  <div v-if="imageAttachments.length" class="space-y-2">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Images</p>
                    <div class="flex flex-wrap gap-3">
                      <a
                        v-for="(att, idx) in imageAttachments"
                        :key="'img-' + idx"
                        :href="attachmentUrl(att)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block overflow-hidden rounded-lg border border-gray-200 bg-gray-50 hover:border-brand-primary transition-colors"
                      >
                        <img
                          :src="imageSrc(att)"
                          :alt="att.original_name || 'Image'"
                          class="h-24 w-24 object-cover"
                          loading="lazy"
                          @error="($e) => ($e.target.style.display = 'none')"
                        />
                        <p class="max-w-[6rem] truncate px-1 py-0.5 text-center text-xs text-gray-600">{{ att.original_name }}</p>
                      </a>
                    </div>
                  </div>
                  <!-- Document attachments (PDF, etc.) – cards with download -->
                  <div v-if="documentAttachments.length" class="space-y-2">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Documents</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <div
                        v-for="(att, idx) in documentAttachments"
                        :key="'doc-' + idx"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3"
                      >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                          <p class="truncate text-sm font-medium text-gray-900">{{ att.original_name || att.name || 'Document' }}</p>
                          <p class="text-xs text-gray-500">{{ att.type || 'Document' }}</p>
                        </div>
                        <a
                          :href="attachmentUrl(att)"
                          target="_blank"
                          rel="noopener noreferrer"
                          download
                          class="flex-shrink-0 rounded p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
                          title="Download"
                        >
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                          </svg>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-6 text-center text-sm text-gray-500">
                  No attachments
                </div>
              </section>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex flex-shrink-0 justify-end gap-3 border-t border-gray-200 bg-gray-50/50 px-6 py-4">
            <button
              v-if="expense && canEdit"
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="goToEdit"
            >
              Edit Expense
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="close"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
