<script setup>
/**
 * Library — Templates & Forms
 * Filters, server-side paginated table, Add/Edit modal,
 * download, export CSV, progressive rendering.
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import api from '@/lib/axios'
import { useTablePageSize } from '@/composables/useTablePageSize'
import { useFormDraft } from '@/composables/useFormDraft'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

// ─── Toast ────────────────────────────────────────────────
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

// ─── State ────────────────────────────────────────────────
const loading     = ref(true)
const canManage   = ref(false)
const canDownload = ref(false)
const rows        = ref([])
const meta        = reactive({ total: 0, per_page: 10, current_page: 1, last_page: 1, from: 0, to: 0 })
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('library')
const sortKey     = ref('created_at')
const sortDir     = ref('desc')

// ─── Meta (filter options) ────────────────────────────────
const categories  = ref([])
const roles       = ref([])

async function fetchMeta() {
  try {
    const { data } = await api.get('/library/documents/meta')
    categories.value = data.data.categories ?? []
    roles.value      = data.data.roles ?? []
  } catch { /* silent */ }
}

// ─── Column definitions ──────────────────────────────────
const ALL_COLUMNS = [
  { key: 'name',             label: 'Document name',    sortable: true  },
  { key: 'category_name',   label: 'Category',          sortable: false },
  { key: 'file_type',       label: 'File type',         sortable: true  },
  { key: 'size_human',      label: 'Size',              sortable: true, sortKey: 'size_bytes' },
  { key: 'allowed_roles',   label: 'Visible to roles',  sortable: false },
  { key: 'uploaded_by_name',label: 'Uploaded by',       sortable: false },
  { key: 'uploaded_on',     label: 'Uploaded on',       sortable: true, sortKey: 'created_at' },
  { key: 'status',          label: 'Status',            sortable: true  },
]

const DEFAULT_VISIBLE = ['name', 'category_name', 'file_type', 'size_human', 'allowed_roles', 'uploaded_by_name']
const visibleColumns = ref([...DEFAULT_VISIBLE])
const columnModalOpen = ref(false)

const activeColumns = computed(() => ALL_COLUMNS.filter(c => visibleColumns.value.includes(c.key)))

const localSelectedCols = ref([])
function openColumnModal() { localSelectedCols.value = [...visibleColumns.value]; columnModalOpen.value = true }
function toggleCol(key) {
  if (localSelectedCols.value.includes(key)) localSelectedCols.value = localSelectedCols.value.filter(c => c !== key)
  else localSelectedCols.value.push(key)
}
function colCheckAll()  { localSelectedCols.value = ALL_COLUMNS.map(c => c.key) }
function colUncheckAll(){ localSelectedCols.value = [] }
function colByDefault() { localSelectedCols.value = [...DEFAULT_VISIBLE] }
function saveColumns() {
  if (localSelectedCols.value.length < 2) { toast('error', 'Select at least 2 columns.'); return }
  visibleColumns.value = ALL_COLUMNS.filter(c => localSelectedCols.value.includes(c.key)).map(c => c.key)
  columnModalOpen.value = false
  toast('success', 'Column preferences updated.')
}

// ─── Filters ──────────────────────────────────────────────
const filters = reactive({ q: '', category_id: '', file_type: '', status: '', date_from: '' })

function clearFilters() { Object.keys(filters).forEach(k => filters[k] = ''); fetchList(1) }

// ─── Fetch list ───────────────────────────────────────────
async function fetchList(page = 1) {
  loading.value = true
  try {
    const params = { ...filters, page, per_page: perPage.value, sort: `${sortKey.value}:${sortDir.value}` }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const { data } = await api.get('/library/documents', { params })
    rows.value = data.data
    Object.assign(meta, data.meta)
    canManage.value  = data.meta?.can_manage ?? false
    canDownload.value = data.meta?.can_download ?? false
  } catch { toast('error', 'Failed to load documents.') }
  finally { loading.value = false }
}

watch(perPage, () => fetchList(1))

function toggleSort(col) {
  if (sortKey.value === col) sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  else { sortKey.value = col; sortDir.value = 'desc' }
  fetchList(1)
}
function sortIcon(col) {
  if (sortKey.value !== col) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

// ─── Roles dropdown ──────────────────────────────────────
const rolesDropdownOpen = ref(false)
const rolesDropdownRef  = ref(null)

function toggleRole(role) {
  if (form.allowed_roles.includes(role)) {
    form.allowed_roles = form.allowed_roles.filter(r => r !== role)
  } else {
    form.allowed_roles.push(role)
  }
}

function onClickOutsideRoles(e) {
  if (rolesDropdownRef.value && !rolesDropdownRef.value.contains(e.target)) {
    rolesDropdownOpen.value = false
  }
}

// ─── Add / Edit modal ─────────────────────────────────────
const showFormModal  = ref(false)
const formMode      = ref('create')
const editDoc       = ref(null)
const formSaving    = ref(false)
const bulkUploadOpen = ref(false)
const formErrors   = reactive({})

const form = reactive({
  name: '', description: '', category_id: '',
  tags: '', allowed_roles: [],
  status: 'active', file: null, change_note: '',
})

const { draftSaving, draftSavedAt, clearDraft } = useFormDraft('library-doc', 'new', form)

function openCreate() {
  formMode.value = 'create'
  editDoc.value = null
  Object.assign(form, { name: '', description: '', category_id: '', tags: '', allowed_roles: [], status: 'active', file: null, change_note: '' })
  rolesDropdownOpen.value = false
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showFormModal.value = true
}

function openEdit(doc) {
  formMode.value = 'edit'
  editDoc.value = doc
  Object.assign(form, {
    name: doc.name, description: doc.description || '', category_id: doc.category_id || '',
    tags: (doc.tags || []).join(', '),
    allowed_roles: doc.allowed_roles || [],
    status: doc.status, file: null, change_note: '',
  })
  rolesDropdownOpen.value = false
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showFormModal.value = true
}

async function submitForm() {
  if (formSaving.value) return
  formSaving.value = true
  Object.keys(formErrors).forEach(k => delete formErrors[k])

  const fd = new FormData()
  fd.append('name', form.name)
  fd.append('description', form.description)
  if (form.category_id) fd.append('category_id', form.category_id)
  const tagArr = form.tags ? form.tags.split(',').map(s => s.trim()).filter(Boolean) : []
  tagArr.forEach((t, i) => fd.append(`tags[${i}]`, t))
  fd.append('visibility', 'internal')
  ;(form.allowed_roles || []).forEach((r, i) => fd.append(`allowed_roles[${i}]`, r))
  fd.append('status', form.status)
  if (form.file) fd.append('file', form.file)
  if (form.change_note) fd.append('change_note', form.change_note)

  try {
    if (formMode.value === 'edit' && editDoc.value) {
      await api.post(`/library/documents/${editDoc.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast('success', 'Document updated.')
    } else {
      await api.post('/library/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast('success', 'Document uploaded.')
    }
    await clearDraft()
    showFormModal.value = false
    fetchList(formMode.value === 'create' ? 1 : meta.current_page)
  } catch (e) {
    if (e?.response?.status === 422) {
      const fe = e.response.data?.errors ?? {}
      Object.keys(fe).forEach(k => { formErrors[k] = Array.isArray(fe[k]) ? fe[k].join(' ') : fe[k] })
    } else toast('error', e?.response?.data?.message || 'Save failed.')
  } finally { formSaving.value = false }
}

function onFileChange(e) { form.file = e.target.files[0] || null }

// (Toggle & Archive removed — replaced by View Detail + Delete)

// ─── Download ─────────────────────────────────────────────
async function downloadDoc(doc) {
  try {
    const res = await api.get(`/library/documents/${doc.id}/download`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    const ext  = doc.file_type === 'image' ? 'png' : doc.file_type
    link.href  = url
    link.setAttribute('download', `${doc.name}.${ext}`)
    document.body.appendChild(link); link.click(); link.remove()
    window.URL.revokeObjectURL(url)
  } catch { toast('error', 'Download failed.') }
}

// ─── View Detail ─────────────────────────────────────────
const showDetail     = ref(false)
const detailDoc      = ref(null)

function openDetail(doc) {
  detailDoc.value = doc
  showDetail.value = true
}

// ─── Delete / Archive ────────────────────────────────────
const confirmDeleteDoc = ref(null)
const deleting         = ref({})

async function deleteDoc(doc) {
  if (deleting.value[doc.id]) return
  deleting.value[doc.id] = true
  try {
    await api.delete(`/library/documents/${doc.id}`)
    toast('success', 'Document deleted.')
    confirmDeleteDoc.value = null
    fetchList(meta.current_page)
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Delete failed.')
  } finally { deleting.value[doc.id] = false }
}

// ─── Bulk Upload ─────────────────────────────────────────
const bulkFiles       = ref([])
const bulkUploading   = ref(false)
const bulkCategoryId  = ref('')

function onBulkFilesSelected(e) {
  const files = Array.from(e.target.files || [])
  if (files.length + bulkFiles.value.length > 20) {
    toast('error', 'Maximum 20 files allowed per batch.')
    return
  }
  bulkFiles.value.push(...files)
}

function removeBulkFile(index) {
  bulkFiles.value.splice(index, 1)
}

function closeBulkModal() {
  bulkUploadOpen.value = false
  bulkFiles.value = []
  bulkCategoryId.value = ''
}

async function submitBulkUpload() {
  if (!bulkFiles.value.length) { toast('error', 'Please select at least one file.'); return }
  bulkUploading.value = true
  try {
    const fd = new FormData()
    bulkFiles.value.forEach(f => fd.append('files[]', f))
    if (bulkCategoryId.value) fd.append('category_id', bulkCategoryId.value)
    const { data } = await api.post('/library/documents/bulk-upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    const msg = data.message || `${data.uploaded?.length ?? 0} document(s) uploaded.`
    if (data.errors?.length) {
      toast('error', `${msg} ${data.errors.length} file(s) failed.`)
    } else {
      toast('success', msg)
    }
    closeBulkModal()
    fetchList(1)
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk upload failed.')
  } finally { bulkUploading.value = false }
}

// ─── Export CSV ───────────────────────────────────────────
const exporting = ref(false)
async function exportCsv() {
  if (exporting.value) return
  exporting.value = true
  try {
    const params = { ...filters }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await api.get('/library/export', { params, responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `library_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link); link.click(); link.remove()
    window.URL.revokeObjectURL(url)
    toast('success', 'Library exported.')
  } catch (e) { toast('error', e?.response?.status === 403 ? 'No permission to export.' : 'Export failed.') }
  finally { exporting.value = false }
}

// ─── Helpers ──────────────────────────────────────────────
function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function fileTypeIcon(ft) {
  const m = { pdf: '📄', docx: '📝', xlsx: '📊', pptx: '📽️', csv: '📋', image: '🖼️' }
  return m[ft] || '📁'
}

function statusClass(s) {
  if (s === 'active')   return 'bg-green-100 text-green-700'
  if (s === 'inactive') return 'bg-yellow-100 text-yellow-700'
  return 'bg-gray-100 text-gray-600'
}

const crumbs = [{ label: 'Settings', to: '/settings' }, { label: 'Library — Templates & Forms' }]

onMounted(() => {
  Promise.allSettled([fetchMeta(), fetchList(1)])
  document.addEventListener('mousedown', onClickOutsideRoles)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutsideRoles)
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <!-- ═══ Header ═══ -->
    <div class="rounded-xl border border-gray-200 bg-gradient-to-r from-teal-50/60 via-white to-white px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 mb-0.5">
            <h1 class="text-xl font-bold text-gray-900">Library – Templates & Forms</h1>
            <Breadcrumbs :items="crumbs" />
          </div>
          <p class="text-sm text-gray-500">Central repository for forms, templates, and documents</p>
          <p v-if="!canManage" class="mt-1 text-xs text-blue-600 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            You are viewing documents shared with your role. Contact admin for upload access.
          </p>
        </div>
        <div class="flex items-center gap-2.5">
          <span v-if="!canManage" class="inline-flex items-center gap-1.5 shrink-0 rounded-lg bg-blue-50 border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            View Only
          </span>
          <button v-if="canManage" :disabled="exporting" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition" @click="exportCsv">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
            Export Library List
          </button>
          <button v-if="canManage" class="inline-flex items-center gap-1.5 rounded-lg border border-teal-600 bg-white px-3.5 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 transition" @click="bulkUploadOpen = true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
            Bulk Upload
          </button>
          <button v-if="canManage" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition shadow-sm" @click="openCreate">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add Document
          </button>
        </div>
      </div>
    </div>

    <div class="px-6 py-5 space-y-4">

      <!-- ═══ Filters ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Document Name</label>
            <input v-model="filters.q" type="text" placeholder="Search documents..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" @keyup.enter="fetchList(1)" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
            <select v-model="filters.category_id" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Categories</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Uploaded From</label>
            <input v-model="filters.date_from" type="date" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">File Type</label>
            <select v-model="filters.file_type" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Types</option>
              <option value="pdf">PDF</option><option value="docx">DOCX</option><option value="xlsx">XLSX</option>
              <option value="pptx">PPTX</option><option value="csv">CSV</option><option value="image">Image</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select v-model="filters.status" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Statuses</option>
              <option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition flex-1" @click="fetchList(1)">Apply</button>
            <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition flex-1" @click="clearFilters">Clear</button>
          </div>
        </div>
      </div>

      <!-- ═══ Toolbar ═══ -->
      <div class="flex items-center justify-end">
        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="openColumnModal">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" /></svg>
          Customize Columns
        </button>
      </div>

      <!-- ═══ Table ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50/80">
                <th
                  v-for="col in activeColumns"
                  :key="col.key"
                  class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap select-none"
                  :class="{ 'cursor-pointer hover:text-gray-700': col.sortable }"
                  @click="col.sortable && toggleSort(col.sortKey || col.key)"
                >
                  <span class="inline-flex items-center gap-1">
                    {{ col.label }}
                    <span v-if="col.sortable" class="text-gray-400">{{ sortIcon(col.sortKey || col.key) }}</span>
                  </span>
                </th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-if="loading">
                <tr v-for="i in perPage" :key="'sk' + i"><td v-for="j in (activeColumns.length + 1)" :key="j" class="px-4 py-3.5"><SkeletonBox class="h-4 w-full" /></td></tr>
              </template>
              <template v-else>
                <tr v-for="doc in rows" :key="doc.id" class="hover:bg-gray-50/70 transition-colors">
                  <td v-for="col in activeColumns" :key="col.key" class="px-4 py-3 text-xs whitespace-nowrap">
                    <!-- Document Name -->
                    <template v-if="col.key === 'name'">
                      <div class="flex items-start gap-2">
                        <span class="text-lg leading-none mt-0.5">{{ fileTypeIcon(doc.file_type) }}</span>
                        <div>
                          <p class="font-medium text-gray-900">{{ doc.name }}</p>
                          <div v-if="doc.tags?.length" class="flex flex-wrap gap-1 mt-1">
                            <span v-for="t in doc.tags.slice(0, 3)" :key="t" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">{{ t }}</span>
                          </div>
                        </div>
                      </div>
                    </template>
                    <!-- Size -->
                    <template v-else-if="col.key === 'size_human'">
                      <span class="text-gray-600">{{ doc.size_human || '—' }}</span>
                    </template>
                    <!-- File type -->
                    <template v-else-if="col.key === 'file_type'">
                      <span class="uppercase text-gray-600">{{ doc.file_type }}</span>
                    </template>
                    <!-- Allowed Roles (visible to) -->
                    <template v-else-if="col.key === 'allowed_roles'">
                      <template v-if="(doc.allowed_roles || []).length > 0">
                        <span v-for="r in (doc.allowed_roles || []).slice(0, 2)" :key="r" class="inline-block rounded bg-green-50 text-green-700 px-1.5 py-0.5 text-[10px] mr-1 capitalize">{{ r.replace(/_/g, ' ') }}</span>
                        <span v-if="(doc.allowed_roles || []).length > 2" class="text-[10px] text-gray-400">+{{ doc.allowed_roles.length - 2 }}</span>
                      </template>
                      <template v-else>
                        <span class="text-xs text-gray-400">Super admin only</span>
                      </template>
                    </template>
                    <!-- Uploaded on -->
                    <template v-else-if="col.key === 'uploaded_on'">
                      <span class="text-gray-600">{{ fmtDate(doc.uploaded_on) }}</span>
                    </template>
                    <!-- Status chip -->
                    <template v-else-if="col.key === 'status'">
                      <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClass(doc.status)">{{ doc.status }}</span>
                    </template>
                    <!-- Default text -->
                    <template v-else>
                      <span class="text-gray-600">{{ doc[col.key] || '—' }}</span>
                    </template>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-center">
                    <div class="inline-flex items-center gap-1">
                      <!-- View detail -->
                      <button class="p-1.5 rounded text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition" title="View" @click.stop="openDetail(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      </button>
                      <!-- Download -->
                      <button v-if="canDownload" class="p-1.5 rounded text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition" title="Download" @click.stop="downloadDoc(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                      </button>
                      <!-- Edit -->
                      <button v-if="canManage" class="p-1.5 rounded text-amber-500 hover:bg-amber-50 hover:text-amber-700 transition" title="Edit" @click.stop="openEdit(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                      </button>
                      <!-- Delete -->
                      <button v-if="canManage" class="p-1.5 rounded text-red-500 hover:bg-red-50 hover:text-red-700 transition" title="Delete" @click.stop="confirmDeleteDoc = doc">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!rows.length"><td :colspan="activeColumns.length + 1" class="px-6 py-12 text-center text-sm text-gray-400">No documents found.</td></tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination footer -->
        <div class="border-t border-gray-200 px-5 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
          <span class="text-sm text-gray-500">Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total }} entries</span>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-500">
              Number of rows
              <select :value="perPage" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm min-w-[85px] focus:border-green-500 focus:ring-1 focus:ring-green-500" @change="e => { setPerPage(e.target.value); fetchList(1) }">
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>
            <button :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3.5 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page - 1)">Previous</button>
            <span class="px-2 py-1.5 text-sm font-medium text-gray-600">{{ meta.current_page }}</span>
            <button :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3.5 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page + 1)">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Add / Edit Document Modal ═══ -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showFormModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden" @click.stop>
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-gray-900">{{ formMode === 'edit' ? 'Edit Document' : 'Add Document' }}</h3>
              <span v-if="draftSavedAt" class="text-xs text-gray-400 flex items-center gap-1">
                <svg v-if="draftSaving" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                <svg v-else class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Draft saved
              </span>
            </div>
            <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="showFormModal = false"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Document name" />
              <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">{{ formErrors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 resize-none" placeholder="Brief description..." />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">None</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
            <!-- Visible to Roles — multi-select dropdown -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Visible to Roles <span class="text-xs text-gray-400 ml-1">(who can view & download)</span>
              </label>
              <div class="relative" ref="rolesDropdownRef">
                <button
                  type="button"
                  class="w-full flex items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-left focus:border-green-500 focus:ring-1 focus:ring-green-500 focus:outline-none"
                  @click="rolesDropdownOpen = !rolesDropdownOpen"
                >
                  <span v-if="form.allowed_roles.length === 0" class="text-gray-400">Select roles...</span>
                  <span v-else-if="form.allowed_roles.length === roles.length" class="text-gray-700 font-medium">All roles selected</span>
                  <span v-else class="text-gray-700">{{ form.allowed_roles.length }} role{{ form.allowed_roles.length !== 1 ? 's' : '' }} selected</span>
                  <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="rolesDropdownOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <!-- Dropdown panel -->
                <div v-if="rolesDropdownOpen" class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg">
                  <!-- Select All / Deselect All -->
                  <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 bg-gray-50">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                      <input
                        type="checkbox"
                        :checked="roles.length > 0 && form.allowed_roles.length === roles.length"
                        :indeterminate="form.allowed_roles.length > 0 && form.allowed_roles.length < roles.length"
                        @change="e => { form.allowed_roles = e.target.checked ? [...roles] : [] }"
                        class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                      />
                      <span class="text-sm font-medium text-gray-700">Select All</span>
                    </label>
                    <span class="text-xs text-gray-400">{{ form.allowed_roles.length }}/{{ roles.length }}</span>
                  </div>
                  <!-- Role options -->
                  <div class="max-h-48 overflow-y-auto py-1">
                    <label
                      v-for="role in roles"
                      :key="role"
                      class="flex items-center gap-2.5 px-3 py-2 cursor-pointer select-none hover:bg-gray-50 transition-colors"
                    >
                      <input
                        type="checkbox"
                        :checked="form.allowed_roles.includes(role)"
                        @change="toggleRole(role)"
                        class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                      />
                      <span class="text-sm text-gray-700 capitalize">{{ role.replace(/_/g, ' ') }}</span>
                    </label>
                    <p v-if="roles.length === 0" class="text-xs text-gray-400 px-3 py-2">No roles available.</p>
                  </div>
                </div>
              </div>
              <!-- Selected role tags -->
              <div v-if="form.allowed_roles.length > 0" class="flex flex-wrap gap-1 mt-2">
                <span v-for="r in form.allowed_roles" :key="r" class="inline-flex items-center gap-1 rounded-full bg-green-50 border border-green-200 px-2 py-0.5 text-xs font-medium text-green-700 capitalize">
                  {{ r.replace(/_/g, ' ') }}
                  <button type="button" class="text-green-500 hover:text-green-700" @click="form.allowed_roles = form.allowed_roles.filter(x => x !== r)">&times;</button>
                </span>
              </div>
              <p v-if="form.allowed_roles.length === 0" class="mt-1 text-xs text-amber-600">
                <svg class="w-3.5 h-3.5 inline mr-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                No roles selected — only super admins will be able to view this document.
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
              <input v-model="form.tags" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="template, form, compliance" />
            </div>
            <div v-if="formMode === 'edit'">
              <label class="block text-sm font-medium text-gray-700 mb-1">Change Note</label>
              <input v-model="form.change_note" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="What changed?" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">File {{ formMode === 'create' ? '*' : '(optional — new version)' }}</label>
              <input type="file" class="w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-green-700 hover:file:bg-green-100" @change="onFileChange" />
              <p v-if="formErrors.file" class="mt-1 text-xs text-red-600">{{ formErrors.file }}</p>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showFormModal = false">Cancel</button>
            <button :disabled="formSaving" class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition" @click="submitForm">
              {{ formSaving ? 'Saving…' : (formMode === 'edit' ? 'Save Changes' : 'Add Document') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ View Document Detail Modal ═══ -->
    <Teleport to="body">
      <div v-if="showDetail && detailDoc" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showDetail = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden" @click.stop>
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50/50">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <h3 class="text-base font-bold text-gray-900">Document Details</h3>
            </div>
            <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="showDetail = false">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-x-4 gap-y-3">
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Document name</p>
                <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ detailDoc.name }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Document code</p>
                <p class="text-sm text-gray-700 mt-0.5 font-mono">{{ detailDoc.document_code || '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Category</p>
                <p class="text-sm text-gray-700 mt-0.5">{{ detailDoc.category_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">File type</p>
                <p class="text-sm text-gray-700 mt-0.5 uppercase">{{ detailDoc.file_type || '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Status</p>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize mt-0.5" :class="statusClass(detailDoc.status)">{{ detailDoc.status }}</span>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Uploaded by</p>
                <p class="text-sm text-gray-700 mt-0.5">{{ detailDoc.uploaded_by_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Uploaded on</p>
                <p class="text-sm text-gray-700 mt-0.5">{{ fmtDate(detailDoc.uploaded_on) }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Size</p>
                <p class="text-sm text-gray-700 mt-0.5">{{ detailDoc.size_human || '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Version</p>
                <p class="text-sm text-gray-700 mt-0.5">v{{ detailDoc.current_version || 1 }}</p>
              </div>
            </div>

            <div v-if="detailDoc.description">
              <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Description</p>
              <p class="text-sm text-gray-700 mt-0.5">{{ detailDoc.description }}</p>
            </div>

            <div v-if="(detailDoc.tags || []).length > 0">
              <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Tags</p>
              <div class="flex flex-wrap gap-1">
                <span v-for="t in detailDoc.tags" :key="t" class="inline-block rounded bg-gray-100 text-gray-600 px-2 py-0.5 text-xs">{{ t }}</span>
              </div>
            </div>

            <div>
              <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Visible to roles</p>
              <div v-if="(detailDoc.allowed_roles || []).length > 0" class="flex flex-wrap gap-1">
                <span v-for="r in detailDoc.allowed_roles" :key="r" class="inline-flex items-center rounded-full bg-green-50 border border-green-200 px-2 py-0.5 text-xs font-medium text-green-700 capitalize">{{ r.replace(/_/g, ' ') }}</span>
              </div>
              <div v-else class="text-sm text-gray-400">Super admin only</div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <button v-if="canDownload" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-white px-3.5 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 transition" @click="downloadDoc(detailDoc)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Download
              </button>
            </div>
            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showDetail = false">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Delete Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="confirmDeleteDoc" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="confirmDeleteDoc = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden" @click.stop>
          <div class="px-6 py-5 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-4">
              <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Delete Document</h3>
            <p class="text-sm text-gray-500">Are you sure you want to delete <strong class="text-gray-700">{{ confirmDeleteDoc.name }}</strong>? This action cannot be undone.</p>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="confirmDeleteDoc = null">Cancel</button>
            <button :disabled="!!deleting[confirmDeleteDoc.id]" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 transition" @click="deleteDoc(confirmDeleteDoc)">
              {{ deleting[confirmDeleteDoc.id] ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Bulk Upload Modal ═══ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="bulkUploadOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-8" @click.self="closeBulkModal">
          <div class="w-full max-w-lg rounded-xl bg-white shadow-xl" @click.stop>
            <div class="flex items-start justify-between border-b px-5 py-4">
              <div>
                <h3 class="text-base font-bold text-gray-900">Bulk Upload Documents</h3>
                <p class="text-xs text-gray-500 mt-0.5">Upload up to 20 files at once. Documents will be named after the file name.</p>
              </div>
              <button type="button" class="-m-1 rounded p-1 text-gray-400 hover:bg-gray-100" @click="closeBulkModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="px-5 py-4 space-y-4">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Category (optional)</label>
                <select v-model="bulkCategoryId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">No category</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Select Files</label>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center hover:border-green-400 hover:bg-green-50/30 transition">
                  <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                  <span class="text-sm text-gray-600 font-medium">Click to select files</span>
                  <span class="text-xs text-gray-400 mt-1">PDF, DOCX, XLSX, PPTX, CSV, Images — max 20 MB each</span>
                  <input type="file" multiple class="hidden" @change="onBulkFilesSelected" accept=".pdf,.doc,.docx,.xls,.xlsx,.pptx,.csv,.png,.jpg,.jpeg,.gif,.webp" />
                </label>
              </div>
              <div v-if="bulkFiles.length" class="space-y-1.5 max-h-48 overflow-y-auto">
                <div v-for="(f, idx) in bulkFiles" :key="idx" class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                  <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span class="text-xs text-gray-700 truncate">{{ f.name }}</span>
                    <span class="text-[10px] text-gray-400 shrink-0">({{ (f.size / 1024).toFixed(0) }} KB)</span>
                  </div>
                  <button type="button" class="p-0.5 rounded text-red-400 hover:text-red-600 hover:bg-red-50" @click="removeBulkFile(idx)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeBulkModal">Cancel</button>
              <button type="button" :disabled="bulkUploading || !bulkFiles.length" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition" @click="submitBulkUpload">
                {{ bulkUploading ? 'Uploading…' : `Upload ${bulkFiles.length} File${bulkFiles.length !== 1 ? 's' : ''}` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ Customize Columns Modal ═══ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="columnModalOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-8" @click.self="columnModalOpen = false">
          <div class="flex max-h-[85vh] min-h-0 w-full max-w-sm flex-col overflow-hidden rounded-xl bg-white shadow-xl" @click.stop>
            <div class="flex items-start justify-between border-b px-5 py-4">
              <div>
                <h3 class="text-base font-bold text-gray-900">Customize Columns</h3>
                <p class="text-xs text-gray-500 mt-0.5">Select which columns to display in the table.</p>
              </div>
              <button type="button" class="-m-1 rounded p-1 text-gray-400 hover:bg-gray-100" @click="columnModalOpen = false">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="flex flex-wrap gap-2 px-5 pt-3 pb-1">
              <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50" @click="colCheckAll">Check All</button>
              <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50" @click="colUncheckAll">Uncheck All</button>
              <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50" @click="colByDefault">By Default</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
              <div class="space-y-1">
                <label v-for="col in ALL_COLUMNS" :key="col.key" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-gray-50">
                  <input type="checkbox" :checked="localSelectedCols.includes(col.key)" class="rounded border-gray-300 text-green-600 focus:ring-green-500" @change="toggleCol(col.key)" />
                  <span class="text-sm text-gray-700">{{ col.label }}</span>
                </label>
              </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="columnModalOpen = false">Cancel</button>
              <button type="button" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="saveColumns">Save</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
