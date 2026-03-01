<script setup>
/**
 * Edit Expense Tracker – same layout as Add form, title "Edit Expense Tracker", pre-populated from expense.
 * Permissions: only users with expense_tracker.edit/update or super admin can edit.
 * Change history: field name, old value, new value, who changed, date & time.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import expensesApi from '@/services/expensesApi'
import { toDdMonYyyyDash, fromDdMmYyyy, fromDdMonYyyyLower } from '@/lib/dateFormat'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const VAT_OPTIONS_DEFAULT = [
  { value: 0, label: '0% (Exempt)' },
  { value: 5, label: '5%' },
  { value: 15, label: '15%' },
]

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const expenseId = computed(() => route.params.id)
const expense = ref(null)
const filterOptions = ref({
  categories: [],
  vat_percent_options: [],
  added_by_users: [],
})
const loading = ref(true)
const loadError = ref(null)
const saving = ref(false)
const error = ref(null)
const fieldErrors = ref({})
const removingAttachmentId = ref(null)
const uploadingAttachments = ref(false)
const newInvoiceFile = ref(null)
const newSupportingFile = ref(null)
const invoiceInputRef = ref(null)
const supportingInputRef = ref(null)
const nativeDateInputRef = ref(null)

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.edit') || permissions.value.includes('expense_tracker.update'))
const loggedInUserId = computed(() => auth.user?.id ?? null)
const addedByDisplayName = computed(() => auth.user?.name || '—')

const vatOptions = computed(() => (filterOptions.value.vat_percent_options?.length ? filterOptions.value.vat_percent_options : VAT_OPTIONS_DEFAULT))

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
  if (!canEdit.value) return
  if (nativeDateInputRef.value?.showPicker) {
    nativeDateInputRef.value.showPicker()
    return
  }
  nativeDateInputRef.value?.focus()
  nativeDateInputRef.value?.click()
}

const dateInputRef = ref(null)

function populateForm() {
  const e = expense.value
  if (!e) return
  const rawDate = e.expense_date_raw || (e.expense_date && e.expense_date.length >= 10 ? e.expense_date.slice(0, 10) : '')
  form.value = {
    expense_date: rawDate,
    expense_date_display: rawDate ? toDdMonYyyyDash(rawDate) : '',
    product_category: e.product_category ?? '',
    invoice_number: e.invoice_number ?? '',
    user_id: loggedInUserId.value,
    vat_percent: e.vat_percent != null ? Number(e.vat_percent) : 0,
    amount_without_vat: e.amount_without_vat != null ? String(e.amount_without_vat) : '',
    product_description: e.product_description ?? '',
    comment: e.comment ?? '',
  }
  form.value.user_id = loggedInUserId.value
}

async function loadExpense() {
  if (!expenseId.value) return
  loading.value = true
  loadError.value = null
  try {
    const res = await expensesApi.show(expenseId.value)
    expense.value = res.data?.data ?? null
    if (expense.value) populateForm()
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load expense.'
    expense.value = null
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const { data } = await expensesApi.filters()
    filterOptions.value = {
      categories: data.categories ?? [],
      vat_percent_options: data.vat_percent_options ?? [],
      added_by_users: data.added_by_users ?? [],
    }
  } catch {
    //
  }
}

function back() {
  router.push('/expenses')
}

async function submit() {
  if (!canEdit.value) return
  error.value = null
  fieldErrors.value = {}
  const d = form.value.expense_date || parseExpenseDate(form.value.expense_date_display)
  if (!d) {
    fieldErrors.value.expense_date = 'Expense Date is required.'
    error.value = 'Please fill all required fields.'
    return
  }
  if (!form.value.product_category?.trim()) {
    fieldErrors.value.product_category = 'Product Category is required.'
    error.value = 'Please fill all required fields.'
    return
  }
  const amount = parseFloat(form.value.amount_without_vat)
  if (Number.isNaN(amount) || amount < 0) {
    error.value = 'Amount Without VAT must be a valid number (0 or more).'
    return
  }
  if (!form.value.product_description?.trim()) {
    fieldErrors.value.product_description = 'Product Description is required.'
    error.value = 'Please fill all required fields.'
    return
  }
  if (!form.value.comment?.trim()) {
    fieldErrors.value.comment = 'Comment / Remarks is required.'
    error.value = 'Please fill all required fields.'
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
    if (loggedInUserId.value != null && loggedInUserId.value !== '') {
      payload.user_id = loggedInUserId.value
    }
    await expensesApi.update(expenseId.value, payload)
    await loadExpense()
  } catch (e) {
    const apiErrors = e?.response?.data?.errors
    if (apiErrors && typeof apiErrors === 'object') {
      fieldErrors.value = {
        expense_date: apiErrors.expense_date?.[0],
        product_category: apiErrors.product_category?.[0],
        product_description: apiErrors.product_description?.[0],
        comment: apiErrors.comment?.[0],
      }
    }
    const msg = e?.response?.data?.message || apiErrors
    error.value = typeof msg === 'string' ? msg : (msg && Object.values(msg).flat?.().length ? Object.values(msg).flat().join(' ') : 'Failed to update expense.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadFilters()
  await loadExpense()
})

watch(expenseId, () => loadExpense())

async function removeAttachment(att) {
  if (!expenseId.value || !att?.id || !canEdit.value) return
  removingAttachmentId.value = att.id
  try {
    await expensesApi.deleteAttachment(expenseId.value, att.id)
    const current = Array.isArray(expense.value?.attachments) ? expense.value.attachments : []
    if (expense.value) {
      expense.value = {
        ...expense.value,
        attachments: current.filter((x) => x?.id !== att.id),
      }
    }
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
  if (!expenseId.value || !newInvoiceFile.value) return
  const fd = new FormData()
  fd.append('invoice', newInvoiceFile.value)
  uploadingAttachments.value = true
  error.value = null
  try {
    const res = await expensesApi.addAttachments(expenseId.value, fd)
    newInvoiceFile.value = null
    if (invoiceInputRef.value) invoiceInputRef.value.value = ''
    const updated = res?.data?.data
    if (updated?.attachments && expense.value) {
      expense.value = {
        ...expense.value,
        attachments: updated.attachments,
      }
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to add attachment.'
  } finally {
    uploadingAttachments.value = false
  }
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-gray-50 py-6 px-4">
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Edit Expense Tracker</h1>
          <Breadcrumbs class="mt-1" />
        </div>
        <button
          type="button"
          class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="back"
        >
          Back to list
        </button>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="loadError" class="rounded-xl border border-red-200 bg-white px-6 py-5 shadow-sm">
        <p class="text-red-700">{{ loadError }}</p>
        <button type="button" class="mt-3 rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200" @click="back">
          Back to list
        </button>
      </div>

      <form v-else-if="expense" class="space-y-5 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden" @submit.prevent="submit">
        <div class="border-b border-gray-200 bg-white px-6 py-4">
          <h2 class="text-lg font-bold text-gray-900">Edit Expense Tracker</h2>
        </div>
        <div class="px-6 py-5 space-y-5">
          <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
          <p v-if="!canEdit" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">You do not have permission to edit this expense.</p>

          <!-- Row 1 -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label for="edit-expense-date" class="mb-1 block text-sm font-medium text-gray-700">Expense Date <span class="text-red-500">*</span></label>
              <div class="relative">
                <input
                  id="edit-expense-date"
                  ref="dateInputRef"
                  v-model="form.expense_date_display"
                  type="text"
                  placeholder="DD-MMM-YYYY"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 pr-9 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="fieldErrors.expense_date ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
                  :readonly="!canEdit"
                  @input="onExpenseDateInput"
                  @click="openDatePicker"
                />
                <input
                  ref="nativeDateInputRef"
                  type="date"
                  tabindex="-1"
                  class="pointer-events-none absolute opacity-0"
                  :value="form.expense_date"
                  @change="(e) => { form.expense_date = e?.target?.value || ''; form.expense_date_display = form.expense_date ? (toDdMonYyyyDash(form.expense_date) || '') : '' }"
                />
                <button v-if="canEdit" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" aria-label="Pick date" @click="openDatePicker">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </button>
              </div>
              <p v-if="fieldErrors.expense_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.expense_date }}</p>
            </div>
            <div>
              <label for="edit-expense-category" class="mb-1 block text-sm font-medium text-gray-700">Product Category <span class="text-red-500">*</span></label>
              <select
                v-if="filterOptions.categories?.length"
                id="edit-expense-category"
                v-model="form.product_category"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :class="fieldErrors.product_category ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
                :disabled="!canEdit"
              >
                <option disabled value="">Select Category</option>
                <option v-for="opt in filterOptions.categories" :key="opt.value" :value="opt.value">{{ opt.label || opt.value }}</option>
              </select>
              <input
                v-else
                id="edit-expense-category"
                v-model="form.product_category"
                type="text"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm"
                :class="fieldErrors.product_category ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
                placeholder="Select Category"
                :readonly="!canEdit"
              />
              <p v-if="fieldErrors.product_category" class="mt-1 text-xs text-red-600">{{ fieldErrors.product_category }}</p>
            </div>
            <div>
              <label for="edit-expense-invoice" class="mb-1 block text-sm font-medium text-gray-700">Invoice Number</label>
              <input
                id="edit-expense-invoice"
                v-model="form.invoice_number"
                type="text"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                placeholder="INV-2024-XXXX"
                :readonly="!canEdit"
              />
            </div>
            <div>
              <label for="edit-expense-added-by" class="mb-1 block text-sm font-medium text-gray-700">Added By</label>
              <input
                id="edit-expense-added-by"
                :value="addedByDisplayName"
                type="text"
                readonly
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
          </div>

          <!-- Row 2: VAT, Amount, VAT Amount (ro), Total (ro) -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label for="edit-expense-vat" class="mb-1 block text-sm font-medium text-gray-700">VAT %</label>
              <select id="edit-expense-vat" v-model="form.vat_percent" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :disabled="!canEdit">
                <option v-for="opt in vatOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label for="edit-expense-amount" class="mb-1 block text-sm font-medium text-gray-700">Amount Without VAT (AED)</label>
              <input
                id="edit-expense-amount"
                v-model="form.amount_without_vat"
                type="number"
                min="0"
                step="0.01"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :readonly="!canEdit"
              />
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

          <!-- Product Description -->
          <div>
            <label for="edit-expense-desc" class="mb-1 block text-sm font-medium text-gray-700">Product Description <span class="text-red-500">*</span></label>
            <input
              id="edit-expense-desc"
              v-model="form.product_description"
              type="text"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :class="fieldErrors.product_description ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
              placeholder="Enter detailed description of the expense"
              :readonly="!canEdit"
            />
            <p v-if="fieldErrors.product_description" class="mt-1 text-xs text-red-600">{{ fieldErrors.product_description }}</p>
          </div>

          <!-- Comment -->
          <div>
            <label for="edit-expense-comment" class="mb-1 block text-sm font-medium text-gray-700">Comment / Remarks <span class="text-red-500">*</span></label>
            <textarea
              id="edit-expense-comment"
              v-model="form.comment"
              rows="3"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 resize-none"
              :class="fieldErrors.comment ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
              placeholder="Add any additional notes or remarks"
              :readonly="!canEdit"
            />
            <p v-if="fieldErrors.comment" class="mt-1 text-xs text-red-600">{{ fieldErrors.comment }}</p>
          </div>

          <!-- Attachments (existing): show + remove -->
          <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-900">Attachments</h3>
            <div v-if="expense.attachments?.length" class="mb-3 flex flex-wrap gap-3">
              <div
                v-for="att in expense.attachments"
                :key="att.id"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
              >
                <a
                  :href="att.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-sm text-gray-700 hover:underline"
                >
                  <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    {{ att.original_name || att.name || att.filename }}
                  </span>
                </a>
                <button
                  v-if="canEdit"
                  type="button"
                  class="ml-1 rounded p-1 text-red-600 hover:bg-red-50 disabled:opacity-50"
                  title="Remove file"
                  :disabled="removingAttachmentId === att.id"
                  @click="removeAttachment(att)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </div>
            </div>
            <p v-else class="mb-3 text-sm text-gray-500">No attachments yet.</p>

            <!-- Add more documents: only Add invoice -->
            <div v-if="canEdit" class="flex flex-wrap items-center gap-4">
              <input
                ref="invoiceInputRef"
                type="file"
                class="hidden"
                accept=".pdf,.doc,.docx,image/*"
                @change="onNewInvoiceChange"
              />
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                :disabled="uploadingAttachments"
                @click="triggerAddInvoice"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                Add invoice
              </button>
              <span v-if="uploadingAttachments" class="text-sm text-gray-500">Uploading…</span>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
          <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="back">Cancel</button>
          <button v-if="canEdit" type="submit" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" :disabled="saving">
            {{ saving ? 'Updating...' : 'Update Expense' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
