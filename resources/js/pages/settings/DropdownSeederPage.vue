<script setup>
/**
 * Dropdown Seeder – Settings page for managing CRM dropdown options.
 * Groups: statuses, emirates, service categories, product types, etc.
 * Supports: CRUD, toggle active/inactive, reorder, and cascade rename.
 * Superadmin only.
 */
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useRouter } from 'vue-router'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'

const router = useRouter()

/* ───── State ───── */
const loading = ref(true)
const saving = ref(false)
const groups = ref([])
const groupedData = ref([])
const selectedGroup = ref('')
const showAddGroup = ref(false)
const newGroupName = ref('')
const error = ref('')
const success = ref('')

/* ───── New option form ───── */
const newOption = ref({ value: '', label: '', sort_order: 0 })

/* ───── Edit state ───── */
const editingId = ref(null)
const editForm = ref({ value: '', label: '', sort_order: 0, is_active: true })

/* ───── Delete state ───── */
const showDeleteModal = ref(false)
const deleteTarget = ref(null)

/* ───── Computed ───── */
const currentGroup = computed(() =>
  groupedData.value.find((g) => g.group === selectedGroup.value)
)
const currentOptions = computed(() => currentGroup.value?.options ?? [])

/* ───── Predefined group templates ───── */
const GROUP_TEMPLATES = [
  { value: 'lead_statuses', label: 'Lead Statuses' },
  { value: 'field_statuses', label: 'Field Submission Statuses' },
  { value: 'field_meeting_statuses', label: 'Field Meeting Statuses' },
  { value: 'customer_support_statuses', label: 'Customer Support Statuses' },
  { value: 'vas_statuses', label: 'VAS Request Statuses' },
  { value: 'special_request_statuses', label: 'Special Request Statuses' },
  { value: 'client_statuses', label: 'Client Statuses' },
  { value: 'emirates', label: 'Emirates' },
  { value: 'service_categories', label: 'Service Categories' },
  { value: 'service_types', label: 'Service Types' },
  { value: 'product_types', label: 'Product Types' },
  { value: 'contract_types', label: 'Contract Types' },
  { value: 'company_categories', label: 'Company Categories' },
  { value: 'expense_statuses', label: 'Expense Statuses' },
  { value: 'team_statuses', label: 'Team Statuses' },
]

const groupLabel = (key) => {
  const tpl = GROUP_TEMPLATES.find((t) => t.value === key)
  return tpl ? tpl.label : key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

/* ───── Load data ───── */
async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const params = {}
    if (selectedGroup.value) params.group = selectedGroup.value
    const { data } = await api.get('/settings/dropdown-seeder', { params })
    groups.value = data.groups ?? []
    groupedData.value = data.data ?? []

    // Auto-select first group if none selected
    if (!selectedGroup.value && groups.value.length > 0) {
      selectedGroup.value = groups.value[0]
    }
  } catch {
    error.value = 'Failed to load dropdown options.'
  } finally {
    loading.value = false
  }
}

/* ───── Add option ───── */
async function addOption() {
  if (!newOption.value.value.trim()) return
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await api.post('/settings/dropdown-seeder', {
      group: selectedGroup.value,
      value: newOption.value.value.trim(),
      label: newOption.value.label.trim() || null,
      sort_order: newOption.value.sort_order || 0,
    })
    newOption.value = { value: '', label: '', sort_order: 0 }
    success.value = 'Option added.'
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to add option.'
  } finally {
    saving.value = false
  }
}

/* ───── Create new group ───── */
async function createGroup() {
  const name = newGroupName.value.trim().toLowerCase().replace(/\s+/g, '_')
  if (!name) return
  selectedGroup.value = name
  showAddGroup.value = false
  newGroupName.value = ''
  // Group will be created when the first option is added
}

/* ───── Edit option ───── */
function startEdit(opt) {
  editingId.value = opt.id
  editForm.value = { value: opt.value, label: opt.label || '', sort_order: opt.sort_order, is_active: opt.is_active }
}

function cancelEdit() {
  editingId.value = null
}

async function saveEdit(id) {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await api.put(`/settings/dropdown-seeder/${id}`, editForm.value)
    editingId.value = null
    success.value = 'Option updated.'
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update option.'
  } finally {
    saving.value = false
  }
}

/* ───── Toggle active ───── */
async function toggleActive(opt) {
  try {
    await api.put(`/settings/dropdown-seeder/${opt.id}`, { is_active: !opt.is_active })
    await loadData()
  } catch {
    error.value = 'Failed to toggle option.'
  }
}

/* ───── Delete ───── */
function confirmDelete(opt) {
  deleteTarget.value = opt
  showDeleteModal.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  try {
    await api.delete(`/settings/dropdown-seeder/${deleteTarget.value.id}`)
    showDeleteModal.value = false
    deleteTarget.value = null
    success.value = 'Option deleted.'
    await loadData()
  } catch {
    error.value = 'Failed to delete option.'
  }
}

/* ───── Lifecycle ───── */
onMounted(() => loadData())
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <button
          type="button"
          class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-primary"
          @click="router.push('/settings')"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Settings
        </button>
        <h1 class="text-2xl font-bold text-gray-900">Dropdown Seeder</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage dropdown values used across the CRM. Renaming a value will cascade-update all existing records.
        </p>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      {{ error }}
      <button class="ml-2 font-medium underline" @click="error = ''">Dismiss</button>
    </div>
    <div v-if="success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
      {{ success }}
      <button class="ml-2 font-medium underline" @click="success = ''">Dismiss</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
    </div>

    <template v-else>
      <!-- Group selector -->
      <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-gray-700">Group:</label>
        <select
          v-model="selectedGroup"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm min-w-[200px]"
          @change="loadData()"
        >
          <option value="" disabled>Select a group</option>
          <option v-for="g in groups" :key="g" :value="g">{{ groupLabel(g) }}</option>
          <option v-for="tpl in GROUP_TEMPLATES.filter((t) => !groups.includes(t.value))" :key="tpl.value" :value="tpl.value">
            {{ tpl.label }} (new)
          </option>
        </select>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="showAddGroup = !showAddGroup"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Custom Group
        </button>
      </div>

      <!-- Custom group input -->
      <div v-if="showAddGroup" class="flex items-end gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Group Key (snake_case)</label>
          <input
            v-model="newGroupName"
            type="text"
            placeholder="e.g. payment_methods"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <button
          type="button"
          class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
          @click="createGroup"
        >
          Use Group
        </button>
      </div>

      <!-- Options table & add form -->
      <div v-if="selectedGroup" class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">
            Add Option to "{{ groupLabel(selectedGroup) }}"
          </h3>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Value *</label>
              <input
                v-model="newOption.value"
                type="text"
                placeholder="e.g. submitted"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48"
                @keydown.enter="addOption"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Label (optional)</label>
              <input
                v-model="newOption.label"
                type="text"
                placeholder="Display label"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Sort Order</label>
              <input
                v-model.number="newOption.sort_order"
                type="number"
                min="0"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-20"
              />
            </div>
            <button
              type="button"
              class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
              :disabled="saving || !newOption.value.trim()"
              @click="addOption"
            >
              {{ saving ? 'Saving...' : 'Add' }}
            </button>
          </div>
        </div>

        <!-- Options listing -->
        <div class="overflow-x-auto rounded-xl border border-gray-200">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-brand-primary border-b-2 border-green-700">
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Order</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Value</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Label</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase">Active</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white uppercase">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="currentOptions.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  No options in this group yet. Add one above.
                </td>
              </tr>
              <template v-for="opt in currentOptions" :key="opt.id">
                <!-- View row -->
                <tr v-if="editingId !== opt.id" class="border-b border-gray-100 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-500">{{ opt.sort_order }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ opt.value }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ opt.label || '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <button
                      type="button"
                      :class="[
                        'inline-flex h-6 w-11 items-center rounded-full transition-colors',
                        opt.is_active ? 'bg-brand-primary' : 'bg-gray-300',
                      ]"
                      @click="toggleActive(opt)"
                    >
                      <span
                        :class="[
                          'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                          opt.is_active ? 'translate-x-6' : 'translate-x-1',
                        ]"
                      />
                    </button>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="mr-2 text-brand-primary hover:text-green-700 text-xs font-medium"
                      @click="startEdit(opt)"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      class="text-red-600 hover:text-red-800 text-xs font-medium"
                      @click="confirmDelete(opt)"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
                <!-- Edit row -->
                <tr v-else class="border-b border-gray-100 bg-amber-50">
                  <td class="px-4 py-2">
                    <input v-model.number="editForm.sort_order" type="number" min="0" class="w-16 rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="editForm.value" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="editForm.label" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2 text-center">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded text-brand-primary" />
                  </td>
                  <td class="px-4 py-2 text-right">
                    <button
                      type="button"
                      class="mr-2 rounded bg-brand-primary px-3 py-1 text-xs font-medium text-white hover:bg-green-600 disabled:opacity-50"
                      :disabled="saving"
                      @click="saveEdit(opt.id)"
                    >
                      Save
                    </button>
                    <button
                      type="button"
                      class="rounded bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-300"
                      @click="cancelEdit"
                    >
                      Cancel
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Cascade info -->
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
          <div class="flex gap-2">
            <svg class="h-5 w-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-blue-800">
              <strong>Cascade Rename:</strong> When you edit a value, all existing records across the CRM that
              use the old value will be automatically updated to the new value. This ensures data consistency.
            </div>
          </div>
        </div>
      </div>

      <!-- No group selected -->
      <div v-else class="rounded-xl border border-gray-200 bg-gray-50 px-6 py-12 text-center">
        <p class="text-gray-500">Select a dropdown group above to manage its options.</p>
      </div>
    </template>

    <!-- Delete confirmation modal -->
    <DeleteOtpModal
      :visible="showDeleteModal"
      title="Delete Dropdown Option"
      :message="'Are you sure you want to delete the option: ' + (deleteTarget?.value || '') + '?'"
      @confirmed="handleDelete"
      @cancelled="showDeleteModal = false; deleteTarget = null"
    />
  </div>
</template>
