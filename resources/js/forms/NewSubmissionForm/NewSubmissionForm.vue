<script setup>
/**
 * Special Request form – Company Name, Account Number, Request Type, Status,
 * Manager, Team Leader, Sales Agent (role-based dropdowns), Comments, and documents.
 */
import { ref, onMounted } from 'vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const form = ref({
  company_name: '',
  account_number: '',
  request_type: '',
  status: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
  comments: '',
})

const options = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})

const REQUEST_TYPE_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'general', label: 'General' },
  { value: 'support', label: 'Support' },
  { value: 'relocation', label: 'Relocation' },
  { value: 'renewal', label: 'Renewal' },
  { value: 'other', label: 'Other' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

const documentFiles = ref([null])
const submitting = ref(false)

onMounted(async () => {
  try {
    const res = await leadSubmissionsApi.getTeamOptions()
    const data = res?.data ?? res ?? {}
    options.value = {
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
    }
  } catch {
    // Keep empty options; form still usable
  }
})

function addDocument() {
  documentFiles.value.push(null)
}

function removeDocument(index) {
  if (documentFiles.value.length <= 1) return
  documentFiles.value.splice(index, 1)
}

function onFileChange(index, event) {
  const file = event.target?.files?.[0]
  if (file) documentFiles.value[index] = file
}

function submit() {
  submitting.value = true
  setTimeout(() => {
    submitting.value = false
    alert('Form design only. Submit will be wired later.')
  }, 300)
}

function reset() {
  form.value = {
    company_name: '',
    account_number: '',
    request_type: '',
    status: '',
    manager_id: null,
    team_leader_id: null,
    sales_agent_id: null,
    comments: '',
  }
  documentFiles.value = [null]
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">Special Request</h3>
      <!-- Row 1: Company Name, Account Number, Request Type, Status (4 in one row) -->
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Company Name</label>
          <input
            v-model="form.company_name"
            type="text"
            placeholder="Enter company name"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Account Number</label>
          <input
            v-model="form.account_number"
            type="text"
            placeholder="Enter account number"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Request Type</label>
          <select
            v-model="form.request_type"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option v-for="o in REQUEST_TYPE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
          <select
            v-model="form.status"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
      </div>
      <!-- Row 2: Manager, Team Leader, Sales Agent (3 in one row) -->
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Manager</label>
          <select
            v-model="form.manager_id"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option :value="null">Select</option>
            <option v-for="m in options.managers" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Team Leader</label>
          <select
            v-model="form.team_leader_id"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option :value="null">Select</option>
            <option v-for="t in options.team_leaders" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Sales Agent</label>
          <select
            v-model="form.sales_agent_id"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option :value="null">Select</option>
            <option v-for="s in options.sales_agents" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>

      <div class="mt-6">
        <label class="mb-1 block text-sm font-medium text-gray-700">Comments</label>
        <textarea
          v-model="form.comments"
          rows="3"
          placeholder="Enter comments"
          class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
        />
      </div>

      <div class="mt-6">
        <label class="mb-2 block text-sm font-medium text-gray-700">Documents</label>
        <div class="space-y-3">
          <div
            v-for="(file, index) in documentFiles"
            :key="index"
            class="flex items-center gap-2"
          >
            <input
              type="file"
              class="block w-full max-w-xs text-sm text-gray-500 file:mr-2 file:rounded file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-green-700 file:hover:bg-green-100"
              @change="onFileChange(index, $event)"
            />
            <button
              v-if="documentFiles.length > 1"
              type="button"
              class="rounded border border-red-200 bg-red-50 px-2 py-1 text-sm text-red-700 hover:bg-red-100"
              @click="removeDocument(index)"
            >
              Remove
            </button>
          </div>
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="addDocument"
          >
            Add document
          </button>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-4">
        <button
          type="button"
          :disabled="submitting"
          class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70"
          @click="submit"
        >
          <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
          {{ submitting ? 'Saving…' : 'Save' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          @click="reset"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>
