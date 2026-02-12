<script setup>
/**
 * Edit Expense modal – same layout as Add form, pre-populated. No change history. Only "Add invoice" for new docs.
 */
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import expensesApi from '@/services/expensesApi'
import { toDdMonYyyy, fromDdMmYyyy, fromDdMonYyyyLower } from '@/lib/dateFormat'

const VAT_OPTIONS_DEFAULT = [
  { value: 0, label: '0% (Exempt)' },
  { value: 5, label: '5%' },
  { value: 15, label: '15%' },
]

const props = defineProps({
  visible: { type: Boolean, default: false },
  expenseId: { type: [Number, String], default: null },
  categories: { type: Array, default: () => [] },
  vatPercentOptions: { type: Array, default: () => [] },
  addedByUsers: { type: Array, default: () => [] },
})

const auth = useAuthStore()
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canEdit = computed(() => isSuperAdmin.value || (auth.user?.permissions ?? []).includes('expense_tracker.edit') || (auth.user?.permissions ?? []).includes('expense_tracker.update'))

const vatOptions = computed(() => (props.vatPercentOptions?.length ? props.vatPercentOptions : VAT_OPTIONS_DEFAULT))

const emit = defineEmits(['close', 'updated'])

const expense = ref(null)
const loading = ref(false)
const loadError = ref(null)
const saving = ref(false)
const error = ref(null)
const removingAttachmentId = ref(null)
const uploadingAttachments = ref(false)
const newInvoiceFile = ref(null)
const invoiceInputRef = ref(null)
const dateInputRef = ref(null)

const form = ref({
  expense_date: '',
  expense_date_display: '',
  product_category: '',
  invoice_number: '',
  user_id: null,
  vat_percent: 0,
  amount_without_vat: '',
  product_description: '',
  comment: '',
})

const vatAmountCurrency = computed(() => {
  const amount = parseFloat(form.value.amount_without_vat)
  if (Number.isNaN(amount) || amount < 0) return 0
  const rate = Number(form.value.vat_percent) || 0
  return Math.round(amount * (rate / 100) * 100) / 100
})
const totalAmount = computed(() => {
  const amount = parseFloat(form.value.amount_without_vat)
  if (Number.isNaN(amount) || amount < 0) return 0
  return Math.round((amount + vatAmountCurrency.value) * 100) / 100
})

function parseExpenseDate(display) {
  if (!display || typeof display !== 'string') return ''
  const t = display.trim()
  if (!t) return ''
  const withDashes = t.toLowerCase().replace(/\s+/g, '-')
  const fromMon = fromDdMonYyyyLower(withDashes)
  if (fromMon) return fromMon
  return fromDdMmYyyy(t)
}
function onExpenseDateInput() {
  form.value.expense_date = parseExpenseDate(form.value.expense_date_display)
}
function openDatePicker() {
  dateInputRef.value?.focus()
}

function populateForm() {
  const e = expense.value
  if (!e) return
  const rawDate = e.expense_date_raw || (e.expense_date && e.expense_date.length >= 10 ? e.expense_date.slice(0, 10) : '')
  form.value = {
    expense_date: rawDate,
    expense_date_display: rawDate ? toDdMonYyyy(rawDate) : '',
    product_category: e.product_category ?? '',
    invoice_number: e.invoice_number ?? '',
    user_id: e.user_id ?? null,
    vat_percent: e.vat_percent != null ? Number(e.vat_percent) : 0,
    amount_without_vat: e.amount_without_vat != null ? String(e.amount_without_vat) : '',
    product_description: e.product_description ?? '',
    comment: e.comment ?? '',
  }
}

async function loadExpense() {
  if (!props.expenseId) return
  loading.value = true
  loadError.value = null
  try {
    const res = await expensesApi.show(props.expenseId)
    expense.value = res.data?.data ?? null
    if (expense.value) populateForm()
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load expense.'
    expense.value = null
  } finally {
    loading.value = false
  }
}

watch([() => props.visible, () => props.expenseId], ([visible, id]) => {
  if (visible && id) {
    loadExpense()
    error.value = null
  }
})

function close() {
  emit('close')
}

async function submit() {
  if (!canEdit.value) return
  error.value = null
  const d = form.value.expense_date || parseExpenseDate(form.value.expense_date_display)
  if (!d) {
    error.value = 'Expense Date is required.'
    return
  }
  if (!form.value.product_category?.trim()) {
    error.value = 'Product Category is required.'
    return
  }
  const amount = parseFloat(form.value.amount_without_vat)
  if (Number.isNaN(amount) || amount < 0) {
    error.value = 'Amount Without VAT must be a valid number (0 or more).'
    return
  }
  if (!form.value.product_description?.trim()) {
    error.value = 'Product Description is required.'
    return
  }
  if (!form.value.comment?.trim()) {
    error.value = 'Comment / Remarks is required.'
    return
  }
  saving.value = true
  try {
    const payload = {
      expense_date: d,
      product_category: form.value.product_category.trim(),
      product_description: form.value.product_description.trim(),
      invoice_number: form.value.invoice_number?.trim() || null,
      comment: form.value.comment.trim(),
      vat_percent: Number(form.value.vat_percent) || 0,
      amount_without_vat: amount,
    }
    if (isSuperAdmin.value && props.addedByUsers?.length && form.value.user_id != null && form.value.user_id !== '') {
      payload.user_id = form.value.user_id
    }
    await expensesApi.update(props.expenseId, payload)
    emit('updated')
    close()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.errors
    error.value = typeof msg === 'string' ? msg : (msg && Object.values(msg).flat?.().length ? Object.values(msg).flat().join(' ') : 'Failed to update expense.')
  } finally {
    saving.value = false
  }
}

async function removeAttachment(att) {
  if (!props.expenseId || !att?.id || !canEdit.value) return
  removingAttachmentId.value = att.id
  try {
    await expensesApi.deleteAttachment(props.expenseId, att.id)
    await loadExpense()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to remove attachment.'
  } finally {
    removingAttachmentId.value = null
  }
}

function triggerAddInvoice() {
  invoiceInputRef.value?.click()
}
function onNewInvoiceChange(e) {
  const f = e.target?.files?.[0]
  newInvoiceFile.value = f || null
  if (f) uploadNewAttachments()
}

async function uploadNewAttachments() {
  if (!props.expenseId || !newInvoiceFile.value) return
  const fd = new FormData()
  fd.append('invoice', newInvoiceFile.value)
  uploadingAttachments.value = true
  error.value = null
  try {
    await expensesApi.addAttachments(props.expenseId, fd)
    newInvoiceFile.value = null
    if (invoiceInputRef.value) invoiceInputRef.value.value = ''
    await loadExpense()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to add attachment.'
  } finally {
    uploadingAttachments.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-400/40 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="edit-expense-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-lg bg-white shadow-xl overflow-hidden border border-gray-200">
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 id="edit-expense-title" class="text-lg font-bold text-gray-900">Edit Expense Tracker</h2>
            <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Close" @click="close">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="loading" class="flex flex-1 items-center justify-center py-12">
            <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>
          <div v-else-if="loadError" class="flex-1 px-6 py-5">
            <p class="text-red-700">{{ loadError }}</p>
          </div>
          <form v-else-if="expense" class="flex-1 min-h-0 overflow-y-auto bg-gray-50/50 px-6 py-5 space-y-5" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
            <p v-if="!canEdit" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">You do not have permission to edit this expense.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="edit-modal-date" class="mb-1 block text-sm font-medium text-gray-700">Expense Date <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input
                    id="edit-modal-date"
                    ref="dateInputRef"
                    v-model="form.expense_date_display"
                    type="text"
                    placeholder="DD-MMM-YYYY"
                    class="w-full rounded border border-gray-300 bg-white px-3 py-2 pr-9 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                    :readonly="!canEdit"
                    @input="onExpenseDateInput"
                  />
                  <button v-if="canEdit" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" aria-label="Pick date" @click="openDatePicker">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  </button>
                </div>
              </div>
              <div>
                <label for="edit-modal-category" class="mb-1 block text-sm font-medium text-gray-700">Product Category <span class="text-red-500">*</span></label>
                <select
                  v-if="categories?.length"
                  id="edit-modal-category"
                  v-model="form.product_category"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :disabled="!canEdit"
                >
                  <option value="">Select Category</option>
                  <option v-for="opt in categories" :key="opt.value" :value="opt.value">{{ opt.label || opt.value }}</option>
                </select>
                <input v-else id="edit-modal-category" v-model="form.product_category" type="text" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Category" :readonly="!canEdit" />
              </div>
              <div>
                <label for="edit-modal-invoice" class="mb-1 block text-sm font-medium text-gray-700">Invoice Number</label>
                <input id="edit-modal-invoice" v-model="form.invoice_number" type="text" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="INV-2024-XXXX" :readonly="!canEdit" />
              </div>
              <div>
                <label for="edit-modal-added-by" class="mb-1 block text-sm font-medium text-gray-700">Added By</label>
                <select id="edit-modal-added-by" v-model="form.user_id" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :disabled="!canEdit || !isSuperAdmin">
                  <option :value="null">Select</option>
                  <option v-for="u in addedByUsers" :key="u.value" :value="u.value">{{ u.label }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="edit-modal-vat" class="mb-1 block text-sm font-medium text-gray-700">VAT %</label>
                <select id="edit-modal-vat" v-model="form.vat_percent" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :disabled="!canEdit">
                  <option v-for="opt in vatOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label for="edit-modal-amount" class="mb-1 block text-sm font-medium text-gray-700">Amount Without VAT (AED)</label>
                <input id="edit-modal-amount" v-model="form.amount_without_vat" type="number" min="0" step="0.01" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :readonly="!canEdit" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">VAT Amount (AED)</label>
                <div class="rounded border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">AED {{ vatAmountCurrency.toFixed(2) }}</div>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Total Amount (AED)</label>
                <div class="rounded border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">AED {{ totalAmount.toFixed(2) }}</div>
              </div>
            </div>

            <div>
              <label for="edit-modal-desc" class="mb-1 block text-sm font-medium text-gray-700">Product Description <span class="text-red-500">*</span></label>
              <input id="edit-modal-desc" v-model="form.product_description" type="text" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Enter detailed description" :readonly="!canEdit" />
            </div>
            <div>
              <label for="edit-modal-comment" class="mb-1 block text-sm font-medium text-gray-700">Comment / Remarks <span class="text-red-500">*</span></label>
              <textarea id="edit-modal-comment" v-model="form.comment" rows="3" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 resize-none" placeholder="Add notes or remarks" :readonly="!canEdit" />
            </div>

            <!-- Attachments: existing + remove; only Add invoice -->
            <div>
              <h3 class="mb-2 text-sm font-semibold text-gray-900">Attachments</h3>
              <div v-if="expense.attachments?.length" class="mb-3 flex flex-wrap gap-3">
                <div v-for="att in expense.attachments" :key="att.id" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                  <a :href="att.url" target="_blank" rel="noopener noreferrer" class="text-sm text-gray-700 hover:underline">
                    <span class="inline-flex items-center gap-1.5">
                      <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                      {{ att.original_name || att.name || att.filename }}
                    </span>
                  </a>
                  <button v-if="canEdit" type="button" class="ml-1 rounded p-1 text-red-600 hover:bg-red-50 disabled:opacity-50" title="Remove file" :disabled="removingAttachmentId === att.id" @click="removeAttachment(att)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </div>
              </div>
              <p v-else class="mb-3 text-sm text-gray-500">No attachments yet.</p>
              <div v-if="canEdit" class="flex flex-wrap items-center gap-4">
                <input ref="invoiceInputRef" type="file" class="hidden" accept=".pdf,.doc,.docx,image/*" @change="onNewInvoiceChange" />
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50" :disabled="uploadingAttachments" @click="triggerAddInvoice">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                  Add invoice
                </button>
                <span v-if="uploadingAttachments" class="text-sm text-gray-500">Uploading…</span>
              </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
              <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="close">Cancel</button>
              <button v-if="canEdit" type="submit" class="rounded bg-[#6BC100] px-4 py-2 text-sm font-medium text-white hover:bg-[#5da800] disabled:opacity-50" :disabled="saving">
                {{ saving ? 'Updating...' : 'Update Expense' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
