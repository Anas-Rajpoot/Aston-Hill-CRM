<script setup>
/**
 * Announcement Center – create and manage system-wide announcements.
 *
 * Sections: KPI counters, collapsible filter panel, paginated table,
 *           create/edit modal, view drawer, toasts, progressive rendering.
 */
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useTablePageSize } from '@/composables/useTablePageSize'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'
import AnnouncementFormModal from '@/components/announcements/AnnouncementFormModal.vue'

const router = useRouter()

// ═══ Toast ═══════════════════════════════════════════════
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function dismissToast() { showToast.value = false }
function toast(type, msg) { toastType.value = type; toastMsg.value = msg; showToast.value = true }

// ═══ Loading & auth ═══════════════════════════════════════
const loading    = ref(true)
const canUpdate  = ref(false)

// ═══ Counters & KPI card filtering ═══════════════════════
const counters = reactive({ total: 0, active: 0, scheduled: 0, expired: 0, disabled: 0 })
const activeCard = ref('') // '' = all, 'active', 'scheduled', 'expired', 'disabled'
const counterCards = computed(() => [
  { label: 'Total',     value: counters.total,     color: 'blue',   icon: 'megaphone', filter: '' },
  { label: 'Active',    value: counters.active,    color: 'green',  icon: 'check',     filter: 'active' },
  { label: 'Scheduled', value: counters.scheduled, color: 'purple', icon: 'calendar',  filter: 'scheduled' },
  { label: 'Expired',   value: counters.expired,   color: 'gray',   icon: 'clock',     filter: 'expired' },
  { label: 'Disabled',  value: counters.disabled,  color: 'red',    icon: 'ban',       filter: 'disabled' },
])

function onCardClick(card) {
  if (activeCard.value === card.filter) {
    activeCard.value = '' // toggle off => show all
  } else {
    activeCard.value = card.filter
  }
  filters.status = activeCard.value
  fetchList(1)
}

// ═══ Filters ══════════════════════════════════════════════
const showFilters = ref(false)
const filters = reactive({ q: '', status: '', priority: '', visibility: '', created_by: '', date_from: '', date_to: '' })

function clearFilters() {
  Object.assign(filters, { q: '', status: '', priority: '', visibility: '', created_by: '', date_from: '', date_to: '' })
  activeCard.value = ''
  fetchList(1)
}

// ═══ Table / Pagination ═══════════════════════════════════
const rows    = ref([])
const meta    = reactive({ total: 0, per_page: 10, current_page: 1, last_page: 1, from: 0, to: 0 })
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('announcements')

async function fetchList(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value, sort: 'publish_at:desc' }
    if (filters.q)          params.q = filters.q
    if (filters.status)     params.status = filters.status
    if (filters.priority)   params.priority = filters.priority
    if (filters.visibility) params.visibility = filters.visibility
    if (filters.created_by) params.created_by = filters.created_by
    if (filters.date_from)  params.date_from = filters.date_from
    if (filters.date_to)    params.date_to = filters.date_to

    const { data } = await api.get('/announcements', { params })
    rows.value = data.data ?? []
    Object.assign(meta, data.meta ?? {})
    canUpdate.value = data.meta?.can_update ?? false

    const c = data.meta?.counters ?? {}
    counters.total     = c.total ?? 0
    counters.active    = c.active ?? 0
    counters.scheduled = c.scheduled ?? 0
    counters.expired   = c.expired ?? 0
    counters.disabled  = c.disabled ?? 0
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to load announcements.')
  } finally { loading.value = false }
}

watch(perPage, () => fetchList(1))

// ═══ Actions ══════════════════════════════════════════════
// Archive / Disable confirmation modal
const archiving = reactive({})
const showConfirmModal = ref(false)
const confirmTarget    = ref(null)
const confirming       = ref(false)

function openArchiveConfirm(ann) {
  if (!canUpdate.value) return
  confirmTarget.value = ann
  showConfirmModal.value = true
}
function closeConfirmModal() {
  if (!confirming.value) {
    showConfirmModal.value = false
    confirmTarget.value = null
  }
}
async function confirmArchive() {
  const ann = confirmTarget.value
  if (!ann || confirming.value) return
  confirming.value = true
  archiving[ann.id] = true
  try {
    await api.delete(`/announcements/${ann.id}`)
    toast('success', 'Announcement disabled successfully.')
    showConfirmModal.value = false
    confirmTarget.value = null
    fetchList(meta.current_page)
  } catch (e) { toast('error', e?.response?.data?.message || 'Failed to disable announcement.') }
  finally { confirming.value = false; delete archiving[ann.id] }
}

// Delete (permanent) confirmation modal
const deleting = reactive({})
const showDeleteModal = ref(false)
const deleteTarget    = ref(null)
const deletePending   = ref(false)

function openDeleteConfirm(ann) {
  if (!canUpdate.value) return
  deleteTarget.value = ann
  showDeleteModal.value = true
}
function closeDeleteModal() {
  if (!deletePending.value) {
    showDeleteModal.value = false
    deleteTarget.value = null
  }
}
async function confirmDelete() {
  const ann = deleteTarget.value
  if (!ann || deletePending.value) return
  deletePending.value = true
  deleting[ann.id] = true
  try {
    await api.delete(`/announcements/${ann.id}/permanent`)
    toast('success', 'Announcement permanently deleted.')
    showDeleteModal.value = false
    deleteTarget.value = null
    fetchList(meta.current_page)
  } catch (e) { toast('error', e?.response?.data?.message || 'Failed to delete announcement.') }
  finally { deletePending.value = false; delete deleting[ann.id] }
}

// Duplicate
const duplicating = reactive({})
async function duplicateAnn(ann) {
  if (!canUpdate.value || duplicating[ann.id]) return
  duplicating[ann.id] = true
  try {
    await api.post(`/announcements/${ann.id}/duplicate`)
    toast('success', 'Announcement duplicated (scheduled for tomorrow).')
    fetchList(1)
  } catch (e) { toast('error', e?.response?.data?.message || 'Duplicate failed.') }
  finally { delete duplicating[ann.id] }
}

// View drawer
const viewAnn   = ref(null)
const showView  = ref(false)
async function openView(ann) {
  try {
    const { data } = await api.get(`/announcements/${ann.id}`)
    viewAnn.value = data.data
    showView.value = true
  } catch { toast('error', 'Failed to load details.') }
}

// Acknowledge
const acking = ref(false)
async function acknowledgeAnn() {
  if (!viewAnn.value || acking.value) return
  acking.value = true
  try {
    await api.post(`/announcements/${viewAnn.value.id}/acknowledge`)
    toast('success', 'Acknowledged!')
    viewAnn.value.ack_count = (viewAnn.value.ack_count ?? 0) + 1
  } catch (e) { toast('error', e?.response?.data?.message || 'Failed.') }
  finally { acking.value = false }
}

// ═══ Form modal ═══════════════════════════════════════════
const showFormModal = ref(false)
const formMode      = ref('create')
const editId        = ref(null)

function openCreate() { formMode.value = 'create'; editId.value = null; showFormModal.value = true }
function openEdit(ann)  { formMode.value = 'edit'; editId.value = ann.id; showFormModal.value = true }

function onSaved() {
  showFormModal.value = false
  toast('success', formMode.value === 'create' ? 'Announcement created!' : 'Announcement updated!')
  fetchList(formMode.value === 'create' ? 1 : meta.current_page)
}

// ═══ Helpers ══════════════════════════════════════════════
function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function statusBadge(s) {
  const map = {
    active:    'bg-green-100 text-green-800',
    scheduled: 'bg-purple-100 text-purple-800',
    expired:   'bg-gray-100 text-gray-600',
    disabled:  'bg-red-100 text-red-800',
    archived:  'bg-red-100 text-red-800',
    draft:     'bg-yellow-100 text-yellow-800',
  }
  return map[s] || 'bg-gray-100 text-gray-600'
}
function typeIcon(t) {
  if (t === 'link') return '🔗'
  if (t === 'banner') return '🏷️'
  return '📝'
}

// ═══ Inline editing (double-click) ═══════════════════════
const editingAnnId    = ref(null)
const editingAnnDraft = reactive({})

function startEditAnn(ann) {
  if (!canUpdate.value) return
  editingAnnId.value = ann.id
  Object.assign(editingAnnDraft, {
    title: ann.title || '',
    type:  ann.type || 'text',
  })
}

function cancelEditAnn() {
  editingAnnId.value = null
  Object.keys(editingAnnDraft).forEach(k => delete editingAnnDraft[k])
}

async function saveEditAnn(ann) {
  if (!editingAnnDraft.title?.trim()) {
    toast('error', 'Title is required.')
    return
  }
  try {
    const { data } = await api.patch(`/announcements/${ann.id}`, {
      title: editingAnnDraft.title,
      type:  editingAnnDraft.type,
    })
    // Update locally
    const idx = rows.value.findIndex(r => r.id === ann.id)
    if (idx !== -1 && data.data) {
      rows.value[idx] = { ...rows.value[idx], ...data.data }
    }
    toast('success', 'Announcement updated.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to update.')
  }
  editingAnnId.value = null
}

// ═══ Mount ════════════════════════════════════════════════
onMounted(() => fetchList(1))
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="toastType==='error'?5000:3000" @dismiss="dismissToast" />

    <!-- ═══ Header ═══ -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-900">Announcement Center</h1>
          <Breadcrumbs />
        </div>
        <p class="mt-1 text-sm text-gray-500">Create and manage system-wide announcements for operational updates and alerts.</p>
      </div>
      <div class="flex items-center gap-3">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showFilters = !showFilters">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
          Filter
        </button>
        <button v-if="canUpdate" type="button" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 transition-colors" @click="openCreate">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Add New Announcement
        </button>
      </div>
    </div>

    <!-- ═══ KPI Counters (clickable to filter) ═══ -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <template v-if="loading && !rows.length">
        <div v-for="i in 5" :key="i" class="rounded-xl border border-gray-200 bg-white p-5"><SkeletonBox width="40%" height="14px" /><SkeletonBox width="30%" height="28px" class="mt-3" /></div>
      </template>
      <template v-else>
        <div
          v-for="c in counterCards" :key="c.label"
          class="rounded-xl border-2 bg-white p-5 flex items-center justify-between cursor-pointer transition-all hover:shadow-md"
          :class="activeCard === c.filter
            ? (c.color === 'blue' ? 'border-blue-500 ring-2 ring-blue-100' : c.color === 'green' ? 'border-green-500 ring-2 ring-green-100' : c.color === 'purple' ? 'border-purple-500 ring-2 ring-purple-100' : c.color === 'red' ? 'border-red-500 ring-2 ring-red-100' : 'border-gray-400 ring-2 ring-gray-100')
            : 'border-gray-200 hover:border-gray-300'"
          @click="onCardClick(c)"
        >
          <div>
            <p class="text-sm text-gray-500">{{ c.label }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ c.value }}</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="{
            'bg-blue-100 text-blue-600': c.color==='blue',
            'bg-green-100 text-green-600': c.color==='green',
            'bg-purple-100 text-purple-600': c.color==='purple',
            'bg-gray-100 text-gray-500': c.color==='gray',
            'bg-red-100 text-red-500': c.color==='red',
          }">
            <svg v-if="c.icon==='megaphone'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
            <svg v-else-if="c.icon==='check'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <svg v-else-if="c.icon==='calendar'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <svg v-else-if="c.icon==='ban'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
        </div>
      </template>
    </div>

    <!-- ═══ Filter Panel (collapsible) ═══ -->
    <Transition name="slide">
      <section v-if="showFilters" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-gray-900">Filter Announcements</h2>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="showFilters = false">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Search Title/Content</label>
            <input v-model="filters.q" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Search…" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select v-model="filters.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="scheduled">Scheduled</option>
              <option value="expired">Expired</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Priority</label>
            <select v-model="filters.priority" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Visibility</label>
            <select v-model="filters.visibility" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">All</option>
              <option value="all_users">All Users</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date From</label>
            <input v-model="filters.date_from" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date To</label>
            <input v-model="filters.date_to" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-3">
          <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="clearFilters">Clear All</button>
          <button type="button" class="rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors" @click="fetchList(1)">Apply Filters</button>
        </div>
      </section>
    </Transition>

    <!-- ═══ Table ═══ -->
    <section class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Skeleton -->
      <div v-if="loading && !rows.length" class="divide-y divide-gray-100">
        <div class="grid grid-cols-8 gap-4 px-6 py-3 bg-gray-50"><SkeletonBox v-for="i in 8" :key="i" width="75%" height="14px" /></div>
        <div v-for="i in 6" :key="i" class="grid grid-cols-8 gap-4 px-6 py-4 items-center">
          <SkeletonBox width="85%" height="16px" /><SkeletonBox width="40px" height="22px" /><SkeletonBox width="80%" height="16px" /><SkeletonBox width="70%" height="14px" /><SkeletonBox width="70%" height="14px" /><SkeletonBox width="60px" height="22px" /><SkeletonBox width="80%" height="14px" /><SkeletonBox width="80px" height="28px" />
        </div>
      </div>

      <!-- Loaded -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-left">
              <th class="px-6 py-3 font-semibold text-gray-600">Title</th>
              <th class="px-4 py-3 font-semibold text-gray-600">Type</th>
              <th class="px-4 py-3 font-semibold text-gray-600">Audience</th>
              <th class="px-4 py-3 font-semibold text-gray-600 whitespace-nowrap">Publish Date</th>
              <th class="px-4 py-3 font-semibold text-gray-600 whitespace-nowrap">Expiry Date</th>
              <th class="px-4 py-3 font-semibold text-gray-600">Status</th>
              <th class="px-4 py-3 font-semibold text-gray-600 whitespace-nowrap">Created By</th>
              <th class="px-4 py-3 font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ann in rows" :key="ann.id" class="hover:bg-gray-50/50 transition-colors" :class="{ 'opacity-60': ann.status === 'disabled' }">
              <!-- Title (editable on double-click) -->
              <td class="px-6 py-3.5 max-w-[260px]" @dblclick="startEditAnn(ann)">
                <template v-if="editingAnnId === ann.id">
                  <input
                    v-model="editingAnnDraft.title"
                    type="text"
                    class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm w-full max-w-[240px]"
                    @keyup.enter="saveEditAnn(ann)"
                    @keyup.escape="cancelEditAnn"
                  />
                </template>
                <template v-else>
                  <span class="line-clamp-1 font-medium text-gray-900 cursor-pointer" :title="canUpdate ? 'Double-click to edit' : ''">{{ ann.title }}</span>
                </template>
              </td>
              <!-- Type (editable on double-click) -->
              <td class="px-4 py-3.5" @dblclick="startEditAnn(ann)">
                <template v-if="editingAnnId === ann.id">
                  <select v-model="editingAnnDraft.type" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs w-full max-w-[100px]">
                    <option value="text">Text</option>
                    <option value="link">Link</option>
                    <option value="image">Image</option>
                    <option value="banner">Banner</option>
                  </select>
                </template>
                <template v-else>
                  <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 cursor-pointer">
                    {{ typeIcon(ann.type) }} {{ ann.type.charAt(0).toUpperCase() + ann.type.slice(1) }}
                  </span>
                </template>
              </td>
              <!-- Audience chips -->
              <td class="px-4 py-3.5">
                <div class="flex flex-wrap gap-1">
                  <template v-if="ann.audience_chips?.length">
                    <span v-for="(chip, ci) in ann.audience_chips.slice(0, 2)" :key="ci"
                      class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 whitespace-nowrap">{{ chip }}</span>
                    <span v-if="ann.audience_chips.length > 2" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                      +{{ ann.audience_chips.length - 2 }}
                    </span>
                  </template>
                  <span v-else class="text-xs text-gray-400">—</span>
                </div>
              </td>
              <!-- Publish Date -->
              <td class="px-4 py-3.5 text-gray-600 whitespace-nowrap text-xs">{{ fmtDate(ann.published_at) }}</td>
              <!-- Expiry Date -->
              <td class="px-4 py-3.5 text-gray-600 whitespace-nowrap text-xs">{{ fmtDate(ann.expire_at) }}</td>
              <!-- Status -->
              <td class="px-4 py-3.5">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusBadge(ann.status)">{{ ann.status }}</span>
              </td>
              <!-- Created By -->
              <td class="px-4 py-3.5 text-gray-600 whitespace-nowrap text-xs">{{ ann.creator_name }}</td>
              <!-- Actions -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-1.5">
                  <template v-if="editingAnnId === ann.id">
                    <!-- Save -->
                    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-green-600 text-white text-xs font-medium hover:bg-green-700 transition-colors" @click="saveEditAnn(ann)">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                      Save
                    </button>
                    <!-- Cancel -->
                    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-gray-300 text-gray-600 text-xs font-medium hover:bg-gray-50 transition-colors" @click="cancelEditAnn">
                      Cancel
                    </button>
                  </template>
                  <template v-else>
                    <!-- View -->
                    <button type="button" class="p-1.5 rounded text-green-600 hover:bg-green-50" title="View" @click="openView(ann)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </button>
                    <!-- Edit (full modal) -->
                    <button v-if="canUpdate" type="button" class="p-1.5 rounded text-blue-600 hover:bg-blue-50" title="Edit" @click="openEdit(ann)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </button>
                    <!-- Duplicate -->
                    <button v-if="canUpdate" type="button" class="p-1.5 rounded text-purple-600 hover:bg-purple-50" :disabled="!!duplicating[ann.id]" title="Duplicate" @click="duplicateAnn(ann)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    </button>
                    <!-- Disable (only if not already disabled) -->
                    <button v-if="canUpdate && ann.status !== 'disabled'" type="button" class="p-1.5 rounded text-amber-500 hover:bg-amber-50" :disabled="!!archiving[ann.id]" title="Disable" @click="openArchiveConfirm(ann)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    </button>
                    <!-- Delete (permanent) -->
                    <button v-if="canUpdate" type="button" class="p-1.5 rounded text-red-500 hover:bg-red-50" :disabled="!!deleting[ann.id]" title="Delete" @click="openDeleteConfirm(ann)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="!rows.length && !loading"><td colspan="8" class="px-6 py-12 text-center text-sm text-gray-400">No announcements found.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-6 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-sm">
        <span class="text-gray-500">Showing {{ meta.from || 0 }} to {{ meta.to || 0 }} of {{ meta.total }} entries</span>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2">
            <span class="text-gray-500 whitespace-nowrap">Number of pages</span>
            <select :value="perPage" class="rounded border border-gray-300 px-2 py-1 text-sm min-w-[60px]" @change="e => { setPerPage(e.target.value); fetchList(1) }">
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="flex gap-1">
            <button :disabled="meta.current_page <= 1" class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium disabled:opacity-40 hover:bg-gray-50" @click="fetchList(meta.current_page - 1)">Previous</button>
            <template v-for="p in meta.last_page" :key="p">
              <button
                class="min-w-[32px] rounded px-2 py-1.5 text-sm font-medium transition-colors"
                :class="p === meta.current_page ? 'bg-green-600 text-white' : 'border border-gray-300 hover:bg-gray-50 text-gray-700'"
                @click="fetchList(p)"
              >{{ p }}</button>
            </template>
            <button :disabled="meta.current_page >= meta.last_page" class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium disabled:opacity-40 hover:bg-gray-50" @click="fetchList(meta.current_page + 1)">Next</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ Announcement Details Popup ═══ -->
    <Teleport to="body">
      <div v-if="showView && viewAnn" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showView = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden" @click.stop>

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Announcement Details</h3>
            <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="showView = false">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

            <!-- Priority + Status badges -->
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                :class="{
                  'bg-red-100 text-red-700': viewAnn.priority === 'critical' || viewAnn.priority === 'high',
                  'bg-blue-100 text-blue-700': viewAnn.priority === 'normal',
                  'bg-gray-100 text-gray-600': viewAnn.priority === 'low'
                }"
              >{{ viewAnn.priority === 'critical' ? 'Critical' : viewAnn.priority === 'high' ? 'High Priority' : viewAnn.priority === 'normal' ? 'Normal Priority' : 'Low Priority' }}</span>
              <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize"
                :class="statusBadge(viewAnn.status)"
              >{{ viewAnn.status }}</span>
            </div>

            <!-- Title -->
            <h2 class="text-base font-bold text-gray-900 leading-snug">{{ viewAnn.title }}</h2>

            <!-- Content card -->
            <div v-if="viewAnn.body" class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
              <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ viewAnn.body }}</p>
            </div>
            <div v-if="viewAnn.link_url && !viewAnn.body" class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
              <a :href="viewAnn.link_url" target="_blank" class="text-sm text-teal-600 hover:underline">{{ viewAnn.link_label || viewAnn.link_url }}</a>
            </div>

            <!-- Metadata grid -->
            <div class="grid grid-cols-2 gap-0 border border-gray-200 rounded-lg overflow-hidden">
              <div class="px-4 py-3 border-b border-r border-gray-200">
                <p class="text-xs text-gray-400 mb-0.5">Created By</p>
                <p class="text-sm font-semibold text-gray-900">{{ viewAnn.creator_name }}</p>
              </div>
              <div class="px-4 py-3 border-b border-gray-200">
                <p class="text-xs text-gray-400 mb-0.5">Created Date</p>
                <p class="text-sm font-semibold text-gray-900">{{ fmtDate(viewAnn.published_at) }}</p>
              </div>
              <div class="px-4 py-3 border-r border-gray-200">
                <p class="text-xs text-gray-400 mb-0.5">Expiry Date</p>
                <p class="text-sm font-semibold text-gray-900">{{ fmtDate(viewAnn.expire_at) }}</p>
              </div>
              <div class="px-4 py-3">
                <p class="text-xs text-gray-400 mb-0.5">Visibility</p>
                <p class="text-sm font-medium text-teal-600">{{ viewAnn.all_users ? 'All Users' : (viewAnn.audience_chips?.join(', ') || '—') }}</p>
              </div>
            </div>

            <!-- Acknowledgement section -->
            <div v-if="viewAnn.require_ack" class="rounded-lg border border-teal-200 bg-teal-50 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-bold text-teal-900">Acknowledgement Required</span>
              </div>

              <!-- Progress -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-xs text-gray-600">Progress</span>
                  <span class="text-xs font-medium text-gray-700">
                    {{ viewAnn.ack_count ?? 0 }} / {{ viewAnn.total_users ?? 0 }} users
                    <template v-if="viewAnn.total_users">({{ Math.round(((viewAnn.ack_count ?? 0) / viewAnn.total_users) * 100) }}%)</template>
                  </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                  <div
                    class="bg-teal-500 h-2 rounded-full transition-all duration-300"
                    :style="{ width: viewAnn.total_users ? Math.round(((viewAnn.ack_count ?? 0) / viewAnn.total_users) * 100) + '%' : '0%' }"
                  />
                </div>
              </div>

              <!-- Acknowledged by names -->
              <div v-if="viewAnn.acknowledged_by?.length">
                <p class="text-xs text-gray-500 mb-1.5">Acknowledged by:</p>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="ack in viewAnn.acknowledged_by"
                    :key="ack.user_id"
                    class="inline-flex items-center rounded-md bg-white border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700"
                  >{{ ack.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end bg-white">
            <button
              type="button"
              class="rounded-lg bg-teal-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-teal-700 transition-colors"
              @click="showView = false"
            >Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Form Modal ═══ -->
    <AnnouncementFormModal
      :show="showFormModal"
      :mode="formMode"
      :announcement-id="editId"
      @close="showFormModal = false"
      @saved="onSaved"
    />

    <!-- ═══ Disable Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="closeConfirmModal">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-semibold text-gray-900">Disable Announcement</h3>
                <p class="text-sm text-gray-500 mt-0.5">Confirm your action</p>
              </div>
            </div>
            <p class="mt-4 text-sm text-gray-600">Are you sure you want to disable this announcement?</p>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              :disabled="confirming"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="closeConfirmModal"
            >Cancel</button>
            <button
              type="button"
              :disabled="confirming"
              class="rounded-lg bg-amber-500 px-5 py-2 text-sm font-medium text-white hover:bg-amber-600 disabled:opacity-50 transition-colors"
              @click="confirmArchive"
            >{{ confirming ? 'Disabling…' : 'Confirm' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Delete Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="closeDeleteModal">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-semibold text-gray-900">Delete Announcement</h3>
                <p class="text-sm text-gray-500 mt-0.5">This action cannot be undone</p>
              </div>
            </div>
            <p class="mt-4 text-sm text-gray-600">Are you sure you want to delete this announcement? All users will no longer be able to view it.</p>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              :disabled="deletePending"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="closeDeleteModal"
            >Cancel</button>
            <button
              type="button"
              :disabled="deletePending"
              class="rounded-lg bg-red-500 px-5 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50 transition-colors"
              @click="confirmDelete"
            >{{ deletePending ? 'Deleting…' : 'Delete Announcement' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
