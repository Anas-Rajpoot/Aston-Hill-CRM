<script setup>
/**
 * Personal Notes – single notepad page with auto-save.
 */
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import personalNotesApi from '@/services/personalNotesApi'
import { toDdMonYyyyLower } from '@/lib/dateFormat'
import Toast from '@/components/Toast.vue'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()

const canView = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'view', ['personal_notes.list', 'personal_notes.view'])
)
const canEdit = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'edit', ['personal_notes.edit', 'personal_notes.update'])
)

const loading = ref(true)
const saving = ref(false)
const noteId = ref(null)
const noteBody = ref('')
const editorRef = ref(null)
const lastSavedBody = ref('')
const lastSavedAt = ref(null)
const saveError = ref('')
const saveTimerMs = 400
let saveTimer = null
let hydrated = false
const boldActive = ref(false)
const highlightActive = ref(false)
let lastEditorRange = null

const saveLabel = computed(() => {
  if (saving.value) return 'Saving...'
  if (saveError.value) return 'Save failed'
  if (!lastSavedAt.value) return 'Not saved yet'
  const iso = lastSavedAt.value
  const day = toDdMonYyyyLower(iso.slice(0, 10)) || '—'
  const time = new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  return `Saved ${day} ${time}`
})

const isReadOnly = computed(() => !canEdit.value)
const statusClass = computed(() => {
  if (saveError.value) return 'text-red-200'
  if (saving.value) return 'text-blue-100'
  return 'text-green-100'
})

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

async function loadNotepad() {
  if (!canView.value) {
    loading.value = false
    return
  }
  loading.value = true
  saveError.value = ''
  try {
    const res = await personalNotesApi.index()
    const list = Array.isArray(res?.data) ? res.data : []
    const first = list.find((n) => String(n?.title || '').trim() === 'Personal Note')
      || list.find((n) => String(n?.body || '').trim() !== '')
      || list[0]
      || null
    noteId.value = first?.id ?? null
    noteBody.value = first?.body ?? ''
    lastSavedBody.value = noteBody.value
    lastSavedAt.value = first?.updated_at ?? null
  } catch {
    saveError.value = 'Failed to load notes.'
    noteId.value = null
    noteBody.value = ''
  } finally {
    loading.value = false
    hydrated = true
  }
}

function queueAutoSave() {
  if (!canEdit.value || !hydrated) return
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    saveNow()
  }, saveTimerMs)
}

function onEditorInput() {
  if (!editorRef.value) return
  // Remove zero-width separators used for caret positioning after formatting.
  noteBody.value = editorRef.value.innerHTML.replace(/\u200B/g, '')
}

function syncEditorHtml() {
  nextTick(() => {
    if (!editorRef.value) return
    const html = String(noteBody.value || '')
    if (editorRef.value.innerHTML !== html) {
      editorRef.value.innerHTML = html
    }
  })
}

function getValidSelectionRange() {
  const root = editorRef.value
  if (!root) return null
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return null
  const range = selection.getRangeAt(0)
  const startNode = range.startContainer
  const endNode = range.endContainer
  if (!root.contains(startNode) || !root.contains(endNode)) return null
  return range
}

function isFormattingElement(node) {
  if (!node || node.nodeType !== 1) return false
  const tag = node.tagName?.toLowerCase?.() || ''
  if (tag === 'b' || tag === 'strong' || tag === 'mark') return true
  if (tag === 'span') {
    const style = String(node.getAttribute('style') || '').toLowerCase()
    return style.includes('background') || style.includes('font-weight')
  }
  return false
}

function pushSpacerOutsideFormatting(spacer) {
  const root = editorRef.value
  if (!root || !spacer) return
  let parent = spacer.parentNode
  while (parent && parent !== root && isFormattingElement(parent)) {
    const grand = parent.parentNode
    if (!grand) break
    grand.insertBefore(spacer, parent.nextSibling)
    parent = spacer.parentNode
  }
}

function selectionStartsOrEndsInFormatting(tags) {
  const root = editorRef.value
  const selection = window.getSelection()
  if (!root || !selection || selection.rangeCount === 0) return false
  const range = selection.getRangeAt(0)
  const wanted = new Set(tags.map((t) => t.toLowerCase()))
  const check = (node) => {
    let cur = node
    while (cur && cur !== root) {
      if (cur.nodeType === 1) {
        const tag = cur.tagName?.toLowerCase?.() || ''
        if (wanted.has(tag)) return true
        if (wanted.has('mark') && tag === 'span') {
          const style = String(cur.getAttribute('style') || '').toLowerCase()
          if (style.includes('background')) {
            // If highlight is explicitly turned off (transparent background),
            // don't treat it as active highlight.
            if (style.includes('transparent')) {
              // Highlight off: keep scanning.
            } else {
              return true
            }
          }
        }
      }
      cur = cur.parentNode
    }
    return false
  }
  return check(range.startContainer) || check(range.endContainer)
}

function cacheSelectionRange() {
  const range = getValidSelectionRange()
  lastEditorRange = range ? range.cloneRange() : null
}

function getActionRange() {
  const current = getValidSelectionRange()
  if (current) return current
  const root = editorRef.value
  if (!root || !lastEditorRange) return null
  if (!root.contains(lastEditorRange.startContainer) || !root.contains(lastEditorRange.endContainer)) return null
  return lastEditorRange.cloneRange()
}

function surroundSelection(tag, attrs = {}) {
  const range = getValidSelectionRange()
  if (!range) return null
  const el = document.createElement(tag)
  for (const [k, v] of Object.entries(attrs)) {
    el.setAttribute(k, String(v))
  }
  try {
    range.surroundContents(el)
  } catch {
    const extracted = range.extractContents()
    el.appendChild(extracted)
    range.insertNode(el)
  }
  const selection = window.getSelection()
  // Insert a zero-width separator after formatted node and place caret there,
  // so subsequent typing stays plain (outside bold/highlight context).
  const spacer = document.createTextNode('\u200B')
  el.parentNode?.insertBefore(spacer, el.nextSibling)
  pushSpacerOutsideFormatting(spacer)
  const caret = document.createRange()
  caret.setStart(spacer, 1)
  caret.collapse(true)
  selection?.removeAllRanges()
  selection?.addRange(caret)
  return el
}

function fragmentHasTag(fragment, tags) {
  const wanted = new Set(tags.map((t) => t.toLowerCase()))
  const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_ELEMENT)
  let node = walker.nextNode()
  while (node) {
    const tag = node.tagName?.toLowerCase?.()
    if (tag && wanted.has(tag)) return true
    // Support legacy highlight markup as span background color.
    if (wanted.has('mark') && tag === 'span') {
      const style = String(node.getAttribute('style') || '').toLowerCase()
      if (style.includes('background')) {
        // If highlight is explicitly turned off (transparent background),
        // don't treat it as active highlight.
        if (style.includes('transparent')) {
          // Highlight off: keep scanning.
        } else {
          return true
        }
      }
    }
    node = walker.nextNode()
  }
  return false
}

function unwrapTagsInFragment(fragment, tags) {
  const wanted = new Set(tags.map((t) => t.toLowerCase()))
  const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_ELEMENT)
  const targets = []
  let node = walker.nextNode()
  while (node) {
    const tag = node.tagName?.toLowerCase?.()
    if (tag && wanted.has(tag)) targets.push(node)
    if (wanted.has('mark') && tag === 'span') {
      const style = String(node.getAttribute('style') || '').toLowerCase()
      if (style.includes('background')) targets.push(node)
    }
    node = walker.nextNode()
  }
  for (const el of targets) {
    const parent = el.parentNode
    if (!parent) continue
    while (el.firstChild) parent.insertBefore(el.firstChild, el)
    parent.removeChild(el)
  }
}

function toggleSelectionFormat({ wrapTag, wrapAttrs = {}, removeTags = [], resetAttrs = {} }) {
  if (isReadOnly.value) return
  const range = getActionRange()
  if (!range) return

  const selection = window.getSelection()
  selection?.removeAllRanges()
  selection?.addRange(range)

  const fragment = range.extractContents()
  const shouldRemove = fragmentHasTag(fragment, removeTags) || selectionStartsOrEndsInFormatting(removeTags)
  let insertedNode = null

  const wrapper = document.createElement(shouldRemove ? 'span' : wrapTag)
  const attrsToApply = shouldRemove ? resetAttrs : wrapAttrs
  for (const [k, v] of Object.entries(attrsToApply)) {
    wrapper.setAttribute(k, String(v))
  }
  if (shouldRemove) {
    // Normalize partial formatted selections by wrapping with explicit reset style.
    // This reliably clears formatting for the selected area only.
    unwrapTagsInFragment(fragment, removeTags)
    wrapper.appendChild(fragment)
    insertedNode = wrapper
  } else {
    for (const [k, v] of Object.entries(wrapAttrs)) {
      wrapper.setAttribute(k, String(v))
    }
    wrapper.appendChild(fragment)
    insertedNode = wrapper
  }

  range.insertNode(insertedNode)

  const spacer = document.createTextNode('\u200B')
  if (insertedNode.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
    range.insertNode(spacer)
  } else {
    insertedNode.parentNode?.insertBefore(spacer, insertedNode.nextSibling)
  }
  pushSpacerOutsideFormatting(spacer)
  const caret = document.createRange()
  caret.setStart(spacer, 1)
  caret.collapse(true)
  selection?.removeAllRanges()
  selection?.addRange(caret)

  onEditorInput()
  updateFormatState()
  lastEditorRange = null
}

function applyBold() {
  toggleSelectionFormat({
    wrapTag: 'strong',
    removeTags: ['strong', 'b'],
    resetAttrs: { style: 'font-weight: 400;' },
  })
}

function applyHighlight() {
  toggleSelectionFormat({
    wrapTag: 'mark',
    wrapAttrs: { style: 'background-color: #fff59d;' },
    removeTags: ['mark'],
    resetAttrs: { style: 'background-color: transparent;' },
  })
}

function nodeHasBold(node) {
  let cur = node
  while (cur && cur !== editorRef.value) {
    if (cur.nodeType === 1) {
      const tag = cur.tagName?.toLowerCase()
      if (tag === 'span') {
        const style = String(cur.getAttribute('style') || '').toLowerCase()
        if (style.includes('font-weight') && (style.includes('400') || style.includes('normal'))) {
          return false
        }
      }
      if (tag === 'b' || tag === 'strong') return true
    }
    cur = cur.parentNode
  }
  return false
}

function nodeHasHighlight(node) {
  let cur = node
  while (cur && cur !== editorRef.value) {
    if (cur.nodeType === 1) {
      const tag = cur.tagName?.toLowerCase()
      if (tag === 'mark') return true
      if (tag === 'span') {
        const style = String(cur.getAttribute('style') || '').toLowerCase()
        if (style.includes('background-color') && style.includes('transparent')) {
          return false
        }
        if (style.includes('background')) return true
      }
    }
    cur = cur.parentNode
  }
  return false
}

function updateFormatState() {
  if (!editorRef.value) return
  const selection = window.getSelection()
  const hasSelection = !!selection && selection.rangeCount > 0 && !selection.isCollapsed
  if (!hasSelection) {
    boldActive.value = false
    highlightActive.value = false
    lastEditorRange = null
    return
  }
  const anchor = selection.anchorNode
  if (!anchor || !editorRef.value.contains(anchor)) {
    boldActive.value = false
    highlightActive.value = false
    lastEditorRange = null
    return
  }
  cacheSelectionRange()
  boldActive.value = nodeHasBold(anchor)
  highlightActive.value = nodeHasHighlight(anchor)
}

async function saveNow() {
  if (!canEdit.value || !hydrated) return
  const body = String(noteBody.value ?? '')
  if (body === lastSavedBody.value) return

  saving.value = true
  saveError.value = ''
  try {
    if (noteId.value) {
      const updated = await personalNotesApi.update(noteId.value, { body })
      noteId.value = updated?.id ?? noteId.value
      lastSavedAt.value = updated?.updated_at ?? new Date().toISOString()
    } else {
      const created = await personalNotesApi.create({ title: 'Personal Note', body })
      noteId.value = created?.id ?? null
      lastSavedAt.value = created?.updated_at ?? created?.created_at ?? new Date().toISOString()
    }
    lastSavedBody.value = body
  } catch (e) {
    saveError.value = e?.response?.data?.message || 'Could not auto-save.'
    toast('error', saveError.value)
  } finally {
    saving.value = false
  }
}

async function refreshFromServerIfClean() {
  // Do not overwrite local typing that is not saved yet.
  if (saving.value || noteBody.value !== lastSavedBody.value) return
  if (!canView.value) return
  try {
    const res = await personalNotesApi.index()
    const list = Array.isArray(res?.data) ? res.data : []
    const first = list.find((n) => String(n?.title || '').trim() === 'Personal Note')
      || list.find((n) => String(n?.body || '').trim() !== '')
      || list[0]
      || null
    const serverBody = String(first?.body ?? '')
    const serverUpdatedAt = first?.updated_at ?? null
    const changed = serverBody !== lastSavedBody.value || serverUpdatedAt !== lastSavedAt.value
    if (changed) {
      noteId.value = first?.id ?? null
      noteBody.value = serverBody
      lastSavedBody.value = serverBody
      lastSavedAt.value = serverUpdatedAt
      syncEditorHtml()
    }
  } catch {
    // Silent on background sync.
  }
}

function onWindowFocus() {
  refreshFromServerIfClean()
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') {
    refreshFromServerIfClean()
  }
}

watch(noteBody, () => {
  queueAutoSave()
})

watch(
  () => loading.value,
  (isLoading) => {
    if (!isLoading) syncEditorHtml()
  }
)

onMounted(() => {
  loadNotepad()
  document.addEventListener('selectionchange', updateFormatState)
  window.addEventListener('focus', onWindowFocus)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onUnmounted(() => {
  if (hydrated && canEdit.value && noteBody.value !== lastSavedBody.value) {
    // Best-effort final flush before leaving the page.
    saveNow()
  }
  if (saveTimer) {
    clearTimeout(saveTimer)
    saveTimer = null
  }
  document.removeEventListener('selectionchange', updateFormatState)
  window.removeEventListener('focus', onWindowFocus)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <div class="flex h-[calc(100vh-4rem)] flex-col bg-white">
    <header class="flex shrink-0 items-center justify-between bg-brand-primary-hover px-4 py-3 text-white">
      <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded bg-white/20">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </span>
        <h1 class="text-lg font-semibold">Personal Notes</h1>
      </div>
      <span class="text-sm" :class="statusClass">{{ saveLabel }}</span>
    </header>

    <main class="min-h-0 flex flex-1 flex-col overflow-hidden bg-gray-100 p-6">
      <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        You do not have permission to view personal notes.
      </div>
      <div v-else-if="loading" class="flex items-center justify-center py-16">
        <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>
      <div v-else class="mx-auto min-h-0 flex h-full w-full max-w-5xl flex-1">
        <div class="min-h-0 flex h-full w-full flex-1 flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex shrink-0 items-center gap-2 border-b border-gray-200 pb-3">
            <button
              type="button"
              class="rounded border px-3 py-1 text-sm font-semibold disabled:opacity-50"
              :class="boldActive ? 'border-brand-primary bg-brand-primary text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
              :disabled="isReadOnly"
              @mousedown.prevent
              @click="applyBold"
            >
              B
            </button>
            <button
              type="button"
              class="rounded border px-3 py-1 text-sm font-medium disabled:opacity-50"
              :class="highlightActive ? 'border-yellow-500 bg-yellow-400 text-gray-900' : 'border-gray-300 bg-yellow-200 text-gray-800 hover:bg-yellow-300'"
              :disabled="isReadOnly"
              @mousedown.prevent
              @click="applyHighlight"
            >
              Highlight
            </button>
          </div>
          <div
            ref="editorRef"
            class="min-h-0 w-full flex-1 overflow-y-auto rounded border border-gray-200 p-3 text-sm text-gray-800 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
            :class="isReadOnly ? 'bg-gray-50' : 'bg-white'"
            :contenteditable="isReadOnly ? 'false' : 'true'"
            @input="onEditorInput"
            @blur="saveNow"
            @focus="updateFormatState"
            @mouseup="updateFormatState"
            @keyup="updateFormatState"
          />
          <p v-if="isReadOnly" class="mt-2 text-xs text-gray-500">Read-only: you do not have edit permission.</p>
          <p v-if="saveError" class="mt-2 text-xs text-red-600">{{ saveError }}</p>
        </div>
      </div>
    </main>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
