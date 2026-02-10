<script setup>
/**
 * Add New Expense modal – design per image: 4 fields per row, date dd-mm-yyyy,
 * Product Category, Invoice Number, Added By; VAT %, Amount Without VAT, computed VAT Amount & Total;
 * Product Description, Comment/Remarks; Attachments (Invoice + Supporting); Cancel + Add Expense.
 */
import { ref, computed, watch } from 'vue'
import expensesApi from '@/services/expensesApi'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'

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

function onExpenseDateInput() {
  form.value.expense_date = fromDdMmYyyy(form.value.expense_date_display) || ''
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
  const d = form.value.expense_date || fromDdMmYyyy(form.value.expense_date_display)
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
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-expense-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="add-expense-title" class="text-lg font-semibold text-gray-900">Add New Expense</h2>
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

          <form class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-5" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ error }}
            </p>

            <!-- Row 1: 4 fields -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="add-expense-date" class="mb-1 block text-sm font-medium text-gray-700">
                  Expense Date <span class="text-red-500">*</span>
                </label>
                <input
                  id="add-expense-date"
                  v-model="form.expense_date_display"
                  type="text"
                  placeholder="dd-mm-yyyy"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @input="onExpenseDateInput"
                />
              </div>
              <div>
                <label for="add-expense-category" class="mb-1 block text-sm font-medium text-gray-700">
                  Product Category <span class="text-red-500">*</span>
                </label>
                <input
                  id="add-expense-category"
                  v-model="form.product_category"
                  type="text"
                  list="add-expense-category-list"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="Select Category"
                />
                <datalist id="add-expense-category-list">
                  <option v-for="opt in categories" :key="opt.value" :value="opt.value" />
                </datalist>
              </div>
              <div>
                <label for="add-expense-invoice" class="mb-1 block text-sm font-medium text-gray-700">
                  Invoice Number
                </label>
                <input
                  id="add-expense-invoice"
                  v-model="form.invoice_number"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="INV-2024-XXXX"
                />
              </div>
              <div v-if="addedByUsers?.length">
                <label for="add-expense-added-by" class="mb-1 block text-sm font-medium text-gray-700">
                  Added By
                </label>
                <select
                  id="add-expense-added-by"
                  v-model="form.user_id"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option :value="null">Select</option>
                  <option v-for="u in addedByUsers" :key="u.value" :value="u.value">
                    {{ u.label }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Row 2: VAT %, Amount Without VAT, VAT Amount (read-only), Total (read-only) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="add-expense-vat" class="mb-1 block text-sm font-medium text-gray-700">
                  VAT %
                </label>
                <select
                  id="add-expense-vat"
                  v-model="form.vat_percent"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  VAT Amount (AED)
                </label>
                <input
                  :value="'AED ' + vatAmountCurrency.toFixed(2)"
                  type="text"
                  readonly
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  Total Amount (AED)
                </label>
                <input
                  :value="'AED ' + totalAmount.toFixed(2)"
                  type="text"
                  readonly
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                />
              </div>
            </div>

            <!-- Row 3: Product Description -->
            <div>
              <label for="add-expense-desc" class="mb-1 block text-sm font-medium text-gray-700">
                Product Description <span class="text-red-500">*</span>
              </label>
              <textarea
                id="add-expense-desc"
                v-model="form.product_description"
                rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                placeholder="Enter detailed description of the expense"
              />
            </div>

            <!-- Row 4: Comment + Attachments -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div>
                <label for="add-expense-comment" class="mb-1 block text-sm font-medium text-gray-700">
                  Comment / Remarks <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="add-expense-comment"
                  v-model="form.comment"
                  rows="3"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  placeholder="Add any additional notes or remarks"
                />
              </div>
              <div>
                <p class="mb-2 text-sm font-semibold text-gray-900">Attachments</p>
                <div class="space-y-3">
                  <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-sm text-gray-600">Invoice Upload</span>
                    <input
                      ref="invoiceInputRef"
                      type="file"
                      class="hidden"
                      accept=".pdf,.jpg,.jpeg,.png"
                      @change="onInvoiceChange"
                    />
                    <button
                      type="button"
                      class="rounded bg-sky-100 px-2 py-1 text-sm font-medium text-sky-700 hover:bg-sky-200"
                      @click="triggerInvoiceUpload"
                    >
                      Upload
                    </button>
                    <span v-if="invoiceFile" class="text-xs text-gray-500 truncate max-w-[120px]">{{ invoiceFile.name }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-sm text-gray-600">Supporting Documents</span>
                    <input
                      ref="supportingInputRef"
                      type="file"
                      class="hidden"
                      accept=".pdf,.jpg,.jpeg,.png"
                      @change="onSupportingChange"
                    />
                    <button
                      type="button"
                      class="rounded bg-sky-100 px-2 py-1 text-sm font-medium text-sky-700 hover:bg-sky-200"
                      @click="triggerSupportingUpload"
                    >
                      Upload
                    </button>
                    <span v-if="supportingFile" class="text-xs text-gray-500 truncate max-w-[120px]">{{ supportingFile.name }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="close"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
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
