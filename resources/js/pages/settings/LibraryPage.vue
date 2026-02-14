<script setup>
/**
 * Library — Templates & Forms
 * Filters, server-side paginated table, Add/Edit modal, version history,
 * download, export CSV, progressive rendering.
 */
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useTablePageSize } from '@/composables/useTablePageSize'
import { useFormDraft } from '@/composables/useFormDraft'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const router = useRouter()

// ─── Toast ────────────────────────────────────────────────
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

// ─── State ────────────────────────────────────────────────
const loading    = ref(true)
const canManage  = ref(false)
const rows       = ref([])
const meta       = reactive({ total: 0, per_page: 10, current_page: 1, last_page: 1, from: 0, to: 0 })
const perPage    = ref(10)
const sortKey    = ref('created_at')
const sortDir    = ref('desc')

// ─── Meta (filter options) ────────────────────────────────
const categories = ref([])
const modules    = ref([])

async function fetchMeta() {
  try {
    const { data } = await api.get('/library/documents/meta')
    categories.value = data.data.categories ?? []
    modules.value    = data.data.modules ?? []
  } catch { /* silent */ }
}

// ─── Filters ──────────────────────────────────────────────
const filters = reactive({ q: '', category_id: '', module: '', file_type: '', status: '' })

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
    canManage.value = data.meta?.can_manage ?? false
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

// ─── Add / Edit modal ─────────────────────────────────────
const showFormModal = ref(false)
const formMode     = ref('create')
const editDoc      = ref(null)
const formSaving   = ref(false)
const formErrors   = reactive({})

const form = reactive({
  name: '', description: '', category_id: '', module_keys: [],
  tags: '', visibility: 'internal', allowed_roles: [], allowed_departments: [],
  status: 'active', file: null, change_note: '',
})

const { draftSaving, draftSavedAt, clearDraft } = useFormDraft('library-doc', 'new', form)

function openCreate() {
  formMode.value = 'create'
  editDoc.value = null
  Object.assign(form, { name: '', description: '', category_id: '', module_keys: [], tags: '', visibility: 'internal', status: 'active', file: null, change_note: '' })
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showFormModal.value = true
}

function openEdit(doc) {
  formMode.value = 'edit'
  editDoc.value = doc
  Object.assign(form, {
    name: doc.name, description: doc.description || '', category_id: doc.category_id || '',
    module_keys: doc.module_keys || [], tags: (doc.tags || []).join(', '),
    visibility: doc.visibility, status: doc.status, file: null, change_note: '',
  })
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
  ;(form.module_keys || []).forEach((m, i) => fd.append(`module_keys[${i}]`, m))
  const tagArr = form.tags ? form.tags.split(',').map(s => s.trim()).filter(Boolean) : []
  tagArr.forEach((t, i) => fd.append(`tags[${i}]`, t))
  fd.append('visibility', form.visibility)
  fd.append('status', form.status)
  if (form.file) fd.append('file', form.file)
  if (form.change_note) fd.append('change_note', form.change_note)

  try {
    if (formMode.value === 'edit' && editDoc.value) {
      fd.append('_method', 'PUT')
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

// ─── Toggle status ────────────────────────────────────────
const toggling = reactive({})
async function toggleDoc(doc) {
  if (!canManage.value || toggling[doc.id]) return
  toggling[doc.id] = true
  try {
    await api.patch(`/library/documents/${doc.id}/toggle`)
    toast('success', 'Status updated.')
    fetchList(meta.current_page)
  } catch { toast('error', 'Toggle failed.') }
  finally { delete toggling[doc.id] }
}

// ─── Archive ──────────────────────────────────────────────
const showArchiveConfirm = ref(false)
const archiveTarget      = ref(null)
const archiving          = ref(false)

function openArchiveConfirm(doc) { archiveTarget.value = doc; showArchiveConfirm.value = true }

async function confirmArchive() {
  if (!archiveTarget.value || archiving.value) return
  archiving.value = true
  try {
    await api.delete(`/library/documents/${archiveTarget.value.id}`)
    toast('success', 'Document archived.')
    showArchiveConfirm.value = false
    archiveTarget.value = null
    fetchList(meta.current_page)
  } catch { toast('error', 'Archive failed.') }
  finally { archiving.value = false }
}

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

// ─── Version History ──────────────────────────────────────
const showVersions = ref(false)
const versionDoc   = ref(null)
const versionList  = ref([])
const versionsLoading = ref(false)

async function openVersions(doc) {
  versionDoc.value = doc
  versionsLoading.value = true
  showVersions.value = true
  try {
    const { data } = await api.get(`/library/documents/${doc.id}/versions`)
    versionList.value = data.data
  } catch { toast('error', 'Failed to load versions.') }
  finally { versionsLoading.value = false }
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

onMounted(() => { Promise.allSettled([fetchMeta(), fetchList(1)]) })
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <!-- ═══ Header ═══ -->
    <div class="bg-white border-b border-gray-200 px-6 py-5">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
          <button class="p-1 rounded hover:bg-gray-100 text-gray-500" @click="router.push('/settings')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          </button>
          <div>
            <div class="flex items-center gap-2 mb-0.5">
              <h1 class="text-xl font-bold text-gray-900">Library — Templates & Forms</h1>
              <Breadcrumbs :items="crumbs" />
            </div>
            <p class="text-sm text-gray-500">Central repository for forms, templates, and documents</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button :disabled="exporting" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition" @click="exportCsv">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
            Export Library List
          </button>
          <button v-if="canManage" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition" @click="openCreate">
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
            <label class="block text-xs font-medium text-gray-500 mb-1">Related Module</label>
            <select v-model="filters.module" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Modules</option>
              <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
            </select>
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
            <button class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition w-full" @click="fetchList(1)">Apply</button>
            <button class="text-xs text-gray-500 hover:text-red-500 whitespace-nowrap" @click="clearFilters">Clear</button>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Showing {{ meta.from ?? 0 }} of {{ meta.total }} documents</p>
      </div>

      <!-- ═══ Table ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50/80">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('name')">Document Name <span class="text-gray-400">{{ sortIcon('name') }}</span></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Related Module</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('file_type')">File Type <span class="text-gray-400">{{ sortIcon('file_type') }}</span></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Uploaded By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('created_at')">Uploaded On <span class="text-gray-400">{{ sortIcon('created_at') }}</span></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('size_bytes')">Size <span class="text-gray-400">{{ sortIcon('size_bytes') }}</span></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('status')">Status <span class="text-gray-400">{{ sortIcon('status') }}</span></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-if="loading">
                <tr v-for="i in perPage" :key="'sk' + i"><td v-for="j in 9" :key="j" class="px-4 py-3.5"><SkeletonBox class="h-4 w-full" /></td></tr>
              </template>
              <template v-else>
                <tr v-for="doc in rows" :key="doc.id" class="hover:bg-gray-50/70 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-start gap-2">
                      <span class="text-lg leading-none mt-0.5">{{ fileTypeIcon(doc.file_type) }}</span>
                      <div>
                        <p class="font-medium text-gray-900 text-xs">{{ doc.name }}</p>
                        <div v-if="doc.tags?.length" class="flex flex-wrap gap-1 mt-1">
                          <span v-for="t in doc.tags.slice(0, 3)" :key="t" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">{{ t }}</span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ doc.category_name || '—' }}</td>
                  <td class="px-4 py-3 text-xs whitespace-nowrap">
                    <span v-for="m in (doc.module_keys || []).slice(0, 2)" :key="m" class="inline-block rounded bg-blue-50 text-blue-700 px-1.5 py-0.5 text-[10px] mr-1">{{ m }}</span>
                    <span v-if="(doc.module_keys || []).length > 2" class="text-[10px] text-gray-400">+{{ doc.module_keys.length - 2 }}</span>
                  </td>
                  <td class="px-4 py-3 text-gray-600 text-xs uppercase whitespace-nowrap">{{ doc.file_type }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ doc.uploaded_by_name }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ fmtDate(doc.uploaded_on) }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ doc.size_human }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClass(doc.status)">{{ doc.status }}</span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center gap-1">
                      <button class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Download" @click.stop="downloadDoc(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                      </button>
                      <button v-if="canManage" class="p-1 rounded text-gray-500 hover:bg-gray-100" title="Edit" @click.stop="openEdit(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                      </button>
                      <button v-if="canManage" class="p-1 rounded hover:bg-gray-100" :class="doc.status === 'active' ? 'text-green-500' : 'text-yellow-500'" title="Toggle status" :disabled="!!toggling[doc.id]" @click.stop="toggleDoc(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                      </button>
                      <button class="p-1 rounded text-purple-500 hover:bg-purple-50" title="Versions" @click.stop="openVersions(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button v-if="canManage && doc.status !== 'archived'" class="p-1 rounded text-red-500 hover:bg-red-50" title="Archive" @click.stop="openArchiveConfirm(doc)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!rows.length"><td colspan="9" class="px-6 py-12 text-center text-sm text-gray-400">No documents found.</td></tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination footer -->
        <div class="border-t border-gray-200 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50/50">
          <div class="flex items-center gap-3 text-sm text-gray-500">
            <span>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total }} entries</span>
            <label class="flex items-center gap-1.5">Number of pages:
              <select :value="perPage" class="rounded border border-gray-300 px-2 py-1 text-sm" @change="e => { setPerPage(e.target.value); fetchList(1) }">
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            </label>
          </div>
          <div class="flex items-center gap-2">
            <button :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page - 1)">Previous</button>
            <span class="px-2 py-1.5 text-xs font-medium text-gray-600">{{ meta.current_page }}</span>
            <button :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page + 1)">Next</button>
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
                <select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                  <option value="">None</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                <select v-model="form.visibility" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                  <option value="public">Public</option>
                  <option value="internal">Internal</option>
                  <option value="restricted">Restricted</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
              <input v-model="form.tags" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="template, form, compliance" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div v-if="formMode === 'edit'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Change Note</label>
                <input v-model="form.change_note" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="What changed?" />
              </div>
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

    <!-- ═══ Version History Modal ═══ -->
    <Teleport to="body">
      <div v-if="showVersions && versionDoc" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showVersions = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[80vh] flex flex-col overflow-hidden" @click.stop>
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Version History — {{ versionDoc.name }}</h3>
            <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="showVersions = false"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-4">
            <div v-if="versionsLoading" class="flex justify-center py-8"><div class="h-6 w-6 animate-spin rounded-full border-2 border-green-600 border-t-transparent" /></div>
            <div v-else-if="!versionList.length" class="py-8 text-center text-sm text-gray-400">No versions found.</div>
            <div v-else class="space-y-3">
              <div v-for="v in versionList" :key="v.id" class="rounded-lg border border-gray-200 p-3 flex items-center justify-between">
                <div>
                  <span class="inline-flex items-center rounded bg-green-100 text-green-700 px-1.5 py-0.5 text-xs font-semibold mr-2">v{{ v.version }}</span>
                  <span class="text-xs text-gray-500">{{ v.uploaded_by }} · {{ fmtDate(v.created_at) }}</span>
                  <p v-if="v.change_note" class="text-xs text-gray-400 mt-0.5">{{ v.change_note }}</p>
                  <p class="text-xs text-gray-400">{{ v.size_human }}</p>
                </div>
              </div>
            </div>
          </div>
          <div class="px-6 py-3 border-t border-gray-200 flex justify-end">
            <button class="rounded-lg bg-gray-600 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700" @click="showVersions = false">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Archive Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showArchiveConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showArchiveConfirm = false">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </div>
              <div>
                <h3 class="text-base font-semibold text-gray-900">Archive Document</h3>
                <p class="text-sm text-gray-500 mt-0.5">This document will be archived</p>
              </div>
            </div>
            <p class="mt-4 text-sm text-gray-600">Are you sure you want to archive "{{ archiveTarget?.name }}"?</p>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button :disabled="archiving" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showArchiveConfirm = false">Cancel</button>
            <button :disabled="archiving" class="rounded-lg bg-red-500 px-5 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50" @click="confirmArchive">{{ archiving ? 'Archiving…' : 'Archive' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
