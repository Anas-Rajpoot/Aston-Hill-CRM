<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import clientsApi from '@/services/clientsApi'
import { toDdMonYyyyDash } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const product = ref(null)

const productId = computed(() => Number(route.params.id || 0))

const closeTarget = computed(() => {
  const returnTo = String(route.query.return_to || '').trim()
  if (returnTo.startsWith('/')) return returnTo

  const parentId = Number(route.query.parent_client_id || 0)
  if (parentId > 0) return `/clients/${parentId}?tab=products-services`

  const rowId = Number(route.params.id || 0)
  return rowId > 0 ? `/clients/${rowId}?tab=products-services` : '/clients'
})

function displayVal(val) {
  return val != null && String(val).trim() !== '' ? String(val) : '—'
}

function displayDate(val) {
  if (!val) return '—'
  return toDdMonYyyyDash(String(val).slice(0, 10)) || '—'
}

function teamName(record, key) {
  if (!record) return '—'
  if (typeof record[key] === 'string' && String(record[key]).trim() !== '') return String(record[key])
  if (record[key]?.name) return record[key].name
  if (key === 'manager' && record.manager_name) return record.manager_name
  if (key === 'manager' && record.manager) return record.manager
  if (key === 'teamLeader' && record.team_leader_name) return record.team_leader_name
  if (key === 'teamLeader' && record.team_leader) return record.team_leader
  if (key === 'salesAgent' && record.sales_agent_name) return record.sales_agent_name
  if (key === 'salesAgent' && record.sales_agent) return record.sales_agent
  return '—'
}

async function loadProduct() {
  loading.value = true
  product.value = null
  try {
    const res = await clientsApi.show(productId.value)
    product.value = res?.data ?? res ?? null
  } catch {
    product.value = null
  } finally {
    loading.value = false
  }
}

function goClose() {
  router.push(closeTarget.value)
}

function goEdit() {
  const id = productId.value
  if (!id) return
  router.push({
    path: `/clients/products/${id}/edit`,
    query: { return_to: closeTarget.value },
  })
}

onMounted(loadProduct)
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="w-full">
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Product & Service Details</h1>
            <Breadcrumbs />
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goClose">
              Close
            </button>
            <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="goEdit">
              Edit Product & Service
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="!product" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load Product & Service details.
      </div>

      <div v-else class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-6 py-5">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Product or Service</h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
              <label class="block text-xs font-medium text-gray-500">Company Name</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.company_name) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Submission Date</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayDate(product.submitted_at) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Manager Name</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ teamName(product, 'manager') }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Team Leader</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ teamName(product, 'teamLeader') }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Sales Agent Name</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ teamName(product, 'salesAgent') }}</div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500">Submission Type</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.submission_type) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Service Category</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.service_category) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Service Type</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.service_type) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Product Type</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.product_type) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Address</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.address) }}</div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500">Product Name</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.product_name) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">MRC</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.mrc) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Quantity</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.quantity) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Offer</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.other) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Migration Numbers</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.migration_numbers) }}</div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500">Activity</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.activity) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Account Number</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.account_number) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Work Order</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.wo_number) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Work Order Status</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.work_order_status) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Activation Date</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayDate(product.activation_date) }}</div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500">Contract Type Term</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.contract_type) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Contract End Date</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayDate(product.contract_end_date) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Clawback / Chum</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.clawback_chum) }}</div>
            </div>
            <div class="lg:col-span-2">
              <label class="block text-xs font-medium text-gray-500">Remarks</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(product.remarks) }}</div>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="block text-xs font-medium text-gray-500">Created By</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.creator?.name || product.creator_name || product.creator) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500">Status</label>
              <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(product.status) }}</div>
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-medium text-gray-500">Additional Note</label>
            <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(product.additional_notes) }}</div>
          </div>

          <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
            <button type="button" class="rounded border border-gray-300 bg-white px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goClose">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
