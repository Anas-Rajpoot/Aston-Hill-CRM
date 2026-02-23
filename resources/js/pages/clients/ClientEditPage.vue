<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import clientsApi from '@/services/clientsApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'

const route = useRoute()
const router = useRouter()

const clientId = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const client = ref(null)
const fieldErrors = ref({})
const generalError = ref('')
const successMessage = ref('')
const csrOptions = ref([])

const COMPANY_CATEGORY_OPTIONS = ['LLC', 'FZCO', 'FZE', 'Branch', 'Other']
const TRADE_LICENSE_AUTHORITIES = ['DED', 'JAFZA', 'DMCC', 'Other']
const BILL_OPTIONS = ['paid', 'unpaid']
const ACCOUNT_MANAGER_OPTIONS = [
  'Imran Siddiqui',
  'Asma Fahmy',
  'Ilhomjon Isomitdinov',
  'Mohamed Shanib',
  'Tanveer Mirza',
]

const form = ref({
  company_name: '',
  account_number: '',
  trade_license_issuing_authority: '',
  company_category: '',
  trade_license_number: '',
  trade_license_expiry_date: '',
  establishment_card_number: '',
  establishment_card_expiry_date: '',
  account_taken_from: '',
  account_mapping_date: '',
  account_transfer_given_to: '',
  account_transfer_given_date: '',
  account_manager_name: '',
  csr_name_1: '',
  first_bill: '',
  second_bill: '',
  third_bill: '',
  fourth_bill: '',
  additional_comment_1: '',
  additional_comment_2: '',
})

async function load() {
  loading.value = true
  generalError.value = ''
  try {
    const [raw, csrRes] = await Promise.all([
      clientsApi.show(clientId.value),
      api.get('/customer-support/csr-options').then(r => r.data).catch(() => ({ csrs: [] })),
    ])
    csrOptions.value = csrRes?.csrs ?? []
    const d = raw?.data ?? raw
    client.value = d
    const cd = d.company_detail ?? {}
    form.value = {
      company_name: d.company_name ?? '',
      account_number: d.account_number ?? '',
      trade_license_issuing_authority: cd.trade_license_issuing_authority ?? '',
      company_category: cd.company_category ?? '',
      trade_license_number: cd.trade_license_number ?? '',
      trade_license_expiry_date: cd.trade_license_expiry_date ?? '',
      establishment_card_number: cd.establishment_card_number ?? '',
      establishment_card_expiry_date: cd.establishment_card_expiry_date ?? '',
      account_taken_from: cd.account_taken_from ?? '',
      account_mapping_date: cd.account_mapping_date ?? '',
      account_transfer_given_to: cd.account_transfer_given_to ?? '',
      account_transfer_given_date: cd.account_transfer_given_date ?? '',
      account_manager_name: cd.account_manager_name ?? '',
      csr_name_1: cd.csr_name_1 ?? '',
      first_bill: cd.first_bill ?? '',
      second_bill: cd.second_bill ?? '',
      third_bill: cd.third_bill ?? '',
      fourth_bill: cd.fourth_bill ?? '',
      additional_comment_1: cd.additional_comment_1 ?? '',
      additional_comment_2: cd.additional_comment_2 ?? '',
    }
  } catch {
    client.value = null
  } finally {
    loading.value = false
  }
}

function inputClass(field) {
  return fieldErrors.value[field]
    ? 'mt-0.5 w-full rounded border border-red-500 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500'
    : 'mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'
}

function selectClass(field) {
  return fieldErrors.value[field]
    ? 'mt-0.5 w-full rounded border border-red-500 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500'
    : 'mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'
}

function validateForm() {
  const f = form.value
  const errs = {}
  if (!f.company_name?.trim()) errs.company_name = 'Company Name is required.'
  if (!f.company_category?.trim()) errs.company_category = 'Company Category is required.'
  if (!f.trade_license_number?.trim()) errs.trade_license_number = 'Trade License Number is required.'
  if (!f.first_bill?.trim()) errs.first_bill = 'First Bill is required.'
  if (!f.second_bill?.trim()) errs.second_bill = 'Second Bill is required.'
  if (!f.third_bill?.trim()) errs.third_bill = 'Third Bill is required.'
  if (!f.fourth_bill?.trim()) errs.fourth_bill = 'Fourth Bill is required.'
  if (!f.account_manager_name?.trim()) errs.account_manager_name = 'Account Manager Name is required.'
  if (!f.csr_name_1?.trim()) errs.csr_name_1 = 'CSR Name is required.'
  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

async function submitForm() {
  generalError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
  if (!validateForm()) {
    generalError.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    const f = form.value

    await clientsApi.update(clientId.value, {
      company_name: f.company_name,
      account_number: f.account_number || null,
    })

    await clientsApi.updateCompanyDetails(clientId.value, {
      trade_license_issuing_authority: f.trade_license_issuing_authority || null,
      company_category: f.company_category || null,
      trade_license_number: f.trade_license_number || null,
      trade_license_expiry_date: f.trade_license_expiry_date || null,
      establishment_card_number: f.establishment_card_number || null,
      establishment_card_expiry_date: f.establishment_card_expiry_date || null,
      account_taken_from: f.account_taken_from || null,
      account_mapping_date: f.account_mapping_date || null,
      account_transfer_given_to: f.account_transfer_given_to || null,
      account_transfer_given_date: f.account_transfer_given_date || null,
      account_manager_name: f.account_manager_name || null,
      csr_name_1: f.csr_name_1 || null,
      first_bill: f.first_bill || null,
      second_bill: f.second_bill || null,
      third_bill: f.third_bill || null,
      fourth_bill: f.fourth_bill || null,
      additional_comment_1: f.additional_comment_1 || null,
      additional_comment_2: f.additional_comment_2 || null,
    })

    successMessage.value = 'Client details updated successfully!'
    setTimeout(() => router.push(`/clients/${clientId.value}`), 1500)
  } catch (e) {
    const errs = e?.response?.data?.errors
    if (errs && typeof errs === 'object') {
      const mapped = {}
      for (const [key, messages] of Object.entries(errs)) {
        mapped[key] = Array.isArray(messages) ? messages[0] : messages
      }
      fieldErrors.value = { ...fieldErrors.value, ...mapped }
    }
    generalError.value = e?.response?.data?.message || 'Failed to save changes.'
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push(`/clients/${clientId.value}`)
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="w-full">
      <!-- Header -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit Client</h1>
            <Breadcrumbs />
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            @click="goBack"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Profile
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <!-- Not found -->
      <div v-else-if="!client" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load client. You may not have permission to view it.
      </div>

      <!-- Form -->
      <form v-else class="space-y-6" @submit.prevent="submitForm">
        <!-- Success -->
        <div v-if="successMessage" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
          {{ successMessage }}
        </div>
        <!-- Error -->
        <div v-if="generalError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ generalError }}
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-5">
            <!-- Company Information -->
            <h2 class="mb-4 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Company Information</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
              <!-- Row 1 -->
              <div>
                <label class="text-xs font-medium text-gray-500">Company Name <span class="text-red-500">*</span></label>
                <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="inputClass('company_name')" @input="fieldErrors.company_name = ''" />
                <p v-if="fieldErrors.company_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.company_name }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Account Number</label>
                <input v-model="form.account_number" type="text" placeholder="Enter account number" :class="inputClass('account_number')" />
                <p v-if="fieldErrors.account_number" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.account_number }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Trade License Issuing Authority</label>
                <select v-model="form.trade_license_issuing_authority" :class="selectClass('trade_license_issuing_authority')">
                  <option value="">Select authority</option>
                  <option v-for="a in TRADE_LICENSE_AUTHORITIES" :key="a" :value="a">{{ a }}</option>
                </select>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Company Category <span class="text-red-500">*</span></label>
                <select v-model="form.company_category" :class="selectClass('company_category')" @change="fieldErrors.company_category = ''">
                  <option value="">Select category</option>
                  <option v-for="c in COMPANY_CATEGORY_OPTIONS" :key="c" :value="c">{{ c }}</option>
                </select>
                <p v-if="fieldErrors.company_category" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.company_category }}</p>
              </div>

              <!-- Row 2 -->
              <div>
                <label class="text-xs font-medium text-gray-500">Trade License Number <span class="text-red-500">*</span></label>
                <input v-model="form.trade_license_number" type="text" placeholder="Enter trade license number" :class="inputClass('trade_license_number')" @input="fieldErrors.trade_license_number = ''" />
                <p v-if="fieldErrors.trade_license_number" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.trade_license_number }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Trade License Expiry Date</label>
                <DateInputDdMmYyyy v-model="form.trade_license_expiry_date" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Establishment Card Number</label>
                <input v-model="form.establishment_card_number" type="text" placeholder="Enter establishment card number" :class="inputClass('establishment_card_number')" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Establishment Card Expiry Date</label>
                <DateInputDdMmYyyy v-model="form.establishment_card_expiry_date" />
              </div>

              <!-- Row 3 -->
              <div>
                <label class="text-xs font-medium text-gray-500">Account Taken From</label>
                <input v-model="form.account_taken_from" type="text" placeholder="Enter value" :class="inputClass('account_taken_from')" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Account Mapping Date</label>
                <DateInputDdMmYyyy v-model="form.account_mapping_date" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Account Transfer Given To</label>
                <input v-model="form.account_transfer_given_to" type="text" placeholder="Enter value" :class="inputClass('account_transfer_given_to')" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Account Transfer Given Date</label>
                <DateInputDdMmYyyy v-model="form.account_transfer_given_date" />
              </div>
            </div>

            <!-- Account Manager & CSR -->
            <h2 class="mb-4 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Account Manager & CSR</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label class="text-xs font-medium text-gray-500">Account Manager Name <span class="text-red-500">*</span></label>
                <select v-model="form.account_manager_name" :class="selectClass('account_manager_name')" @change="fieldErrors.account_manager_name = ''">
                  <option value="" disabled>Select Account Manager</option>
                  <option v-for="am in ACCOUNT_MANAGER_OPTIONS" :key="am" :value="am">{{ am }}</option>
                </select>
                <p v-if="fieldErrors.account_manager_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.account_manager_name }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">CSR Name 1 <span class="text-red-500">*</span></label>
                <select v-model="form.csr_name_1" :class="selectClass('csr_name_1')" @change="fieldErrors.csr_name_1 = ''">
                  <option value="">Select CSR</option>
                  <option v-for="csr in csrOptions" :key="csr.id" :value="csr.name">{{ csr.name }}</option>
                </select>
                <p v-if="fieldErrors.csr_name_1" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.csr_name_1 }}</p>
              </div>
            </div>

            <!-- Billing -->
            <h2 class="mb-4 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Billing</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label class="text-xs font-medium text-gray-500">First Bill <span class="text-red-500">*</span></label>
                <select v-model="form.first_bill" :class="selectClass('first_bill')" @change="fieldErrors.first_bill = ''">
                  <option value="">Paid / Unpaid</option>
                  <option v-for="b in BILL_OPTIONS" :key="b" :value="b">{{ b }}</option>
                </select>
                <p v-if="fieldErrors.first_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.first_bill }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Second Bill <span class="text-red-500">*</span></label>
                <select v-model="form.second_bill" :class="selectClass('second_bill')" @change="fieldErrors.second_bill = ''">
                  <option value="">Paid / Unpaid</option>
                  <option v-for="b in BILL_OPTIONS" :key="b" :value="b">{{ b }}</option>
                </select>
                <p v-if="fieldErrors.second_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.second_bill }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Third Bill <span class="text-red-500">*</span></label>
                <select v-model="form.third_bill" :class="selectClass('third_bill')" @change="fieldErrors.third_bill = ''">
                  <option value="">Paid / Unpaid</option>
                  <option v-for="b in BILL_OPTIONS" :key="b" :value="b">{{ b }}</option>
                </select>
                <p v-if="fieldErrors.third_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.third_bill }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Fourth Bill <span class="text-red-500">*</span></label>
                <select v-model="form.fourth_bill" :class="selectClass('fourth_bill')" @change="fieldErrors.fourth_bill = ''">
                  <option value="">Paid / Unpaid</option>
                  <option v-for="b in BILL_OPTIONS" :key="b" :value="b">{{ b }}</option>
                </select>
                <p v-if="fieldErrors.fourth_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.fourth_bill }}</p>
              </div>
            </div>

            <!-- Additional Comments -->
            <h2 class="mb-4 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Additional Comments</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
              <div>
                <label class="text-xs font-medium text-gray-500">Additional Comment 1</label>
                <textarea v-model="form.additional_comment_1" rows="3" :class="inputClass('additional_comment_1')" placeholder="Enter comment" />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-500">Additional Comment 2</label>
                <textarea v-model="form.additional_comment_2" rows="3" :class="inputClass('additional_comment_2')" placeholder="Enter comment" />
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
              <button
                type="button"
                class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="goBack"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="inline-flex items-center gap-2 rounded bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
              >
                <svg v-if="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
