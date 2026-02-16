<script setup>
/**
 * Add New Expense modal – design per image: Expense Date (DD-MMM-YYYY), Product Category dropdown,
 * Invoice Number, Added By; VAT %, Amount Without VAT, VAT Amount & Total (read-only); Product Description,
 * Comment/Remarks; Attachments (Invoice + Supporting); Cancel + Add Expense. Green primary button, light blue uploads.
 */
import { ref, computed, watch } from 'vue'
import expensesApi from '@/services/expensesApi'
import { fromDdMmYyyy, fromDdMonYyyyLower } from '@/lib/dateFormat'

const VAT_OPTIONS_DEFAULT = [
  { value: 0, label: '0% (Exempt)' },
  { value: 5, label: '5%' },
  { value: 15, label: '15%' },
]

const props = defineProps({
  visible: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  vatPercentOptions: { type: Array, default: () => [] },
  addedByUsers: { type: Array, default: () => [] },
  currentUserId: { type: [Number, String], default: null },
})

const vatOptions = computed(() => (props.vatPercentOptions?.length ? props.vatPercentOptions : VAT_OPTIONS_DEFAULT))

const emit = defineEmits(['close', 'created'])

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
const invoiceFile = ref(null)
const supportingFile = ref(null)
const invoiceInputRef = ref(null)
const supportingInputRef = ref(null)
const dateInputRef = ref(null)
const submitting = ref(false)
const error = ref(null)

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

watch(() => props.visible, (visible) => {
  if (visible) {
    form.value = {
      expense_date: '',
      expense_date_display: '',
      product_category: '',
      invoice_number: '',
      user_id: props.currentUserId ?? null,
      vat_percent: 0,
      amount_without_vat: '',
      product_description: '',
      comment: '',
    }
    invoiceFile.value = null
    supportingFile.value = null
    error.value = null
  }
})

function close() {
  emit('close')
}

/** Parse DD-MMM-YYYY or DD-MM-YYYY to yyyy-mm-dd for API */
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

function triggerInvoiceUpload() {
  invoiceInputRef.value?.click()
}

function triggerSupportingUpload() {
  supportingInputRef.value?.click()
}

function onInvoiceChange(e) {
  const f = e.target?.files?.[0]
  invoiceFile.value = f || null
}

function onSupportingChange(e) {
  const f = e.target?.files?.[0]
  supportingFile.value = f || null
}

async function submit() {
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

  submitting.value = true
  try {
    const hasFiles = invoiceFile.value || supportingFile.value
    if (hasFiles) {
      const fd = new FormData()
      fd.append('expense_date', d)
      fd.append('product_category', form.value.product_category.trim())
      fd.append('product_description', form.value.product_description.trim())
      fd.append('invoice_number', form.value.invoice_number?.trim() || '')
      fd.append('comment', form.value.comment.trim())
      fd.append('vat_percent', String(Number(form.value.vat_percent) || 0))
      fd.append('amount_without_vat', String(amount))
      if (props.addedByUsers?.length && form.value.user_id != null && form.value.user_id !== '') {
        fd.append('user_id', String(form.value.user_id))
      }
      if (invoiceFile.value) fd.append('invoice', invoiceFile.value)
      if (supportingFile.value) fd.append('supporting_documents', supportingFile.value)
      await expensesApi.create(fd)
    } else {
      const payload = {
        expense_date: d,
        product_category: form.value.product_category.trim(),
        product_description: form.value.product_description.trim(),
        invoice_number: form.value.invoice_number?.trim() || null,
        comment: form.value.comment.trim(),
        vat_percent: Number(form.value.vat_percent) || 0,
        amount_without_vat: amount,
      }
      if (props.addedByUsers?.length && form.value.user_id != null && form.value.user_id !== '') {
        payload.user_id = form.value.user_id
      }
      await expensesApi.create(payload)
    }
    emit('created')
    close()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.errors
    error.value = typeof msg === 'string' ? msg : (msg && Object.values(msg).flat?.().length ? Object.values(msg).flat().join(' ') : 'Failed to create expense.')
  } finally {
    submitting.value = false
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
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-400/40 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-expense-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-lg bg-white shadow-xl overflow-hidden border border-gray-200">
          <!-- Header -->
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 id="add-expense-title" class="text-lg font-bold text-gray-900">Add New Expense</h2>
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

          <form class="flex-1 min-h-0 overflow-y-auto bg-gray-50/50 px-6 py-5 space-y-5" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ error }}
            </p>

            <!-- Row 1: Expense Date, Product Category, Invoice Number, Added By -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="add-expense-date" class="mb-1 block text-sm font-medium text-gray-700">
                  Expense Date <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    id="add-expense-date"
                    ref="dateInputRef"
                    v-model="form.expense_date_display"
                    type="text"
                    placeholder="DD-MMM-YYYY"
                    class="w-full rounded border border-gray-300 bg-white px-3 py-2 pr-9 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                    @input="onExpenseDateInput"
                  />
                  <button
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Pick date"
                    @click="openDatePicker"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </button>
                </div>
              </div>
              <div>
                <label for="add-expense-category" class="mb-1 block text-sm font-medium text-gray-700">
                  Product Category <span class="text-red-500">*</span>
                </label>
                <select
                  v-if="categories?.length"
                  id="add-expense-category"
                  v-model="form.product_category"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option value="">Select Category</option>
                  <option v-for="opt in categories" :key="opt.value" :value="opt.value">
                    {{ opt.label || opt.value }}
                  </option>
                </select>
                <input
                  v-else
                  id="add-expense-category"
                  v-model="form.product_category"
                  type="text"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="Select Category"
                />
              </div>
              <div>
                <label for="add-expense-invoice" class="mb-1 block text-sm font-medium text-gray-700">
                  Invoice Number
                </label>
                <input
                  id="add-expense-invoice"
                  v-model="form.invoice_number"
                  type="text"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="INV-2024-XXXX"
                />
              </div>
              <div>
                <label for="add-expense-added-by" class="mb-1 block text-sm font-medium text-gray-700">
                  Added By
                </label>
                <select
                  id="add-expense-added-by"
                  v-model="form.user_id"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option :value="null">Select</option>
                  <option v-for="u in addedByUsers" :key="u.value" :value="u.value">
                    {{ u.label }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Row 2: VAT %, Amount Without VAT (editable), VAT Amount (read-only), Total (read-only) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="add-expense-vat" class="mb-1 block text-sm font-medium text-gray-700">
                  VAT %
                </label>
                <select
                  id="add-expense-vat"
                  v-model="form.vat_percent"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option v-for="opt in vatOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label for="add-expense-amount" class="mb-1 block text-sm font-medium text-gray-700">
                  Amount Without VAT (AED)
                </label>
                <input
                  id="add-expense-amount"
                  v-model="form.amount_without_vat"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  VAT Amount (AED)
                </label>
                <div class="rounded border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                  AED {{ vatAmountCurrency.toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  Total Amount (AED)
                </label>
                <div class="rounded border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                  AED {{ totalAmount.toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Product Description: single-line, full width -->
            <div>
              <label for="add-expense-desc" class="mb-1 block text-sm font-medium text-gray-700">
                Product Description <span class="text-red-500">*</span>
              </label>
              <input
                id="add-expense-desc"
                v-model="form.product_description"
                type="text"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                placeholder="Enter detailed description of the expense"
              />
            </div>

            <!-- Comment / Remarks -->
            <div>
              <label for="add-expense-comment" class="mb-1 block text-sm font-medium text-gray-700">
                Comment / Remarks <span class="text-red-500">*</span>
              </label>
              <textarea
                id="add-expense-comment"
                v-model="form.comment"
                rows="3"
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 resize-none"
                placeholder="Add any additional notes or remarks"
              />
            </div>

            <!-- Attachments: horizontal layout, document icon + Upload (light blue) -->
            <div>
              <h3 class="mb-3 text-sm font-semibold text-gray-900">Attachments</h3>
              <div class="flex flex-wrap items-center gap-8">
                <div class="flex items-center gap-2">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded border border-gray-200 bg-white text-gray-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <input
                    ref="invoiceInputRef"
                    type="file"
                    class="hidden"
                    accept=".pdf,.jpg,.jpeg,.png"
                    @change="onInvoiceChange"
                  />
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded px-2 py-1.5 text-sm font-medium text-[#1890FF] hover:bg-blue-50 transition-colors"
                    @click="triggerInvoiceUpload"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </button>
                  <span v-if="invoiceFile" class="max-w-[120px] truncate text-xs text-gray-500">{{ invoiceFile.name }}</span>
                  <span class="text-sm text-gray-600">Invoice Upload</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded border border-gray-200 bg-white text-gray-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <input
                    ref="supportingInputRef"
                    type="file"
                    class="hidden"
                    accept=".pdf,.jpg,.jpeg,.png"
                    @change="onSupportingChange"
                  />
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded px-2 py-1.5 text-sm font-medium text-[#1890FF] hover:bg-blue-50 transition-colors"
                    @click="triggerSupportingUpload"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </button>
                  <span v-if="supportingFile" class="max-w-[120px] truncate text-xs text-gray-500">{{ supportingFile.name }}</span>
                  <span class="text-sm text-gray-600">Supporting Documents</span>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
              <button
                type="button"
                class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                @click="close"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                :disabled="submitting"
              >
                {{ submitting ? 'Adding...' : 'Add Expense' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
