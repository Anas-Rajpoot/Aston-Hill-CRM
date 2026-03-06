<script setup>
/**
 * Personal Notes – notepad-style view. Header with Add/Edit/Delete, sidebar note list, main note content, footer with Created/Last updated (dd-mon-yyyy) and prev/next.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import personalNotesApi from '@/services/personalNotesApi'
import { toDdMonYyyyLower } from '@/lib/dateFormat'
import Toast from '@/components/Toast.vue'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const canView = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'view', ['personal_notes.list', 'personal_notes.view'])
)
const canCreate = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'create', ['personal_notes.create', 'personal_notes.add'])
)
const canEdit = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'edit', ['personal_notes.edit', 'personal_notes.update'])
)
const canDelete = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'delete', ['personal_notes.delete'])
)

const notes = ref([])
const currentIndex = ref(0)
const loading = ref(true)
const showDeleteModal = ref(false)
const deleting = ref(false)
const editing = ref(false)
const saving = ref(false)
const editForm = ref({ title: '', body: '' })

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const currentNote = computed(() => {
  const list = notes.value
  const i = currentIndex.value
  if (!list.length || i < 0 || i >= list.length) return null
  return list[i]
})

const currentPosition = computed(() => {
  const total = notes.value.length
  if (total === 0) return { current: 0, total: 0 }
  return { current: currentIndex.value + 1, total }
})

const canGoPrev = computed(() => currentIndex.value > 0)
const canGoNext = computed(() => currentIndex.value < notes.value.length - 1 && notes.value.length > 0)

function formatDateDisplay(isoString) {
  if (!isoString || typeof isoString !== 'string') return '—'
  const ymd = isoString.trim().slice(0, 10)
  return toDdMonYyyyLower(ymd) || '—'
}

function goPrev() {
  if (!canGoPrev.value) return
  editing.value = false
  currentIndex.value -= 1
  updateRoute()
}

function goNext() {
  if (!canGoNext.value) return
  editing.value = false
  currentIndex.value += 1
  updateRoute()
}

function updateRoute() {
  const note = currentNote.value
  if (note?.id && route.params.id !== String(note.id)) {
    router.replace({ path: `/personal-notes/${note.id}` })
  }
}

function selectNoteByIndex(index) {
  if (index >= 0 && index < notes.value.length) {
    editing.value = false
    currentIndex.value = index
    updateRoute()
  }
}

async function loadNotes() {
  if (!canView.value) {
    notes.value = []
    currentIndex.value = 0
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await personalNotesApi.index()
    notes.value = res.data ?? []
    const idFromRoute = route.params.id
    if (idFromRoute && notes.value.length) {
      const idx = notes.value.findIndex((n) => String(n.id) === String(idFromRoute))
      currentIndex.value = idx >= 0 ? idx : 0
    } else {
      currentIndex.value = 0
    }
  } catch {
    notes.value = []
    currentIndex.value = 0
  } finally {
    loading.value = false
  }
}

function addNote() {
  if (!canCreate.value) return
  router.push('/personal-notes/create')
}

function editNote() {
  if (!canEdit.value) return
  const note = currentNote.value
  if (!note?.id) return
  editForm.value = { title: note.title || '', body: note.body || '' }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
  editForm.value = { title: '', body: '' }
}

async function saveEdit() {
  if (!canEdit.value) return
  const note = currentNote.value
  if (!note?.id) return
  saving.value = true
  try {
    await personalNotesApi.update(note.id, editForm.value)
    // Update local data
    const idx = notes.value.findIndex(n => n.id === note.id)
    if (idx >= 0) {
      notes.value[idx] = { ...notes.value[idx], ...editForm.value, updated_at: new Date().toISOString() }
    }
    editing.value = false
    toast('success', 'Note updated successfully.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to update note.')
  } finally {
    saving.value = false
  }
}

function openDeleteModal() {
  if (!canDelete.value) return
  if (currentNote.value?.id) showDeleteModal.value = true
}

function closeDeleteModal() {
  if (!deleting.value) showDeleteModal.value = false
}

async function confirmDeleteNote() {
  if (!canDelete.value) return
  const note = currentNote.value
  if (!note?.id) return
  deleting.value = true
  try {
    await personalNotesApi.delete(note.id)
    toast('success', 'Note deleted successfully.')
    showDeleteModal.value = false
    await loadNotes()
    if (currentIndex.value >= notes.value.length && notes.value.length > 0) {
      currentIndex.value = notes.value.length - 1
    } else if (notes.value.length === 0) {
      router.replace('/personal-notes')
    } else {
      updateRoute()
    }
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete note.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadNotes()
})

watch(
  () => route.params.id,
  (id) => {
    if (!id || !notes.value.length) return
    const idx = notes.value.findIndex((n) => String(n.id) === String(id))
    if (idx >= 0) currentIndex.value = idx
  }
)
</script>

<template>
  <div class="flex h-[calc(100vh-4rem)] flex-col bg-white">
    <!-- Dark teal header -->
    <header class="flex shrink-0 items-center justify-between bg-brand-primary-hover px-4 py-3 text-white">
      <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded bg-white/20">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </span>
        <h1 class="text-lg font-semibold">Personal Notes</h1>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="canCreate"
          type="button"
          class="rounded p-2 text-white hover:bg-white/20"
          aria-label="Add note"
          @click="addNote"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="rounded p-2 text-white hover:bg-white/20"
          aria-label="Edit note"
          :disabled="!currentNote"
          @click="editNote"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
          </svg>
        </button>
        <button
          v-if="canDelete"
          type="button"
          class="rounded p-2 text-white hover:bg-white/20"
          aria-label="Delete note"
          :disabled="!currentNote"
          @click="openDeleteModal"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Main: sidebar + note content -->
    <div class="flex min-h-0 flex-1">
      <!-- Left sidebar: note list (oval placeholders) -->
      <aside class="flex w-14 shrink-0 flex-col items-center gap-2 border-r border-gray-300 bg-gray-100 py-4">
        <div
          v-for="(note, idx) in notes"
          :key="note.id"
          class="h-8 w-8 shrink-0 rounded-full transition-colors"
          :class="idx === currentIndex ? 'bg-brand-primary-hover' : 'bg-gray-300'"
          :title="note.title"
          role="button"
          tabindex="0"
          @click="selectNoteByIndex(idx)"
          @keydown.enter.space.prevent="selectNoteByIndex(idx)"
        />
      </aside>

      <!-- Note content -->
      <main class="min-w-0 flex-1 overflow-y-auto bg-gray-100 p-6">
        <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          You do not have permission to view personal notes.
        </div>
        <div v-if="loading" class="flex items-center justify-center py-16">
          <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
        <template v-else-if="currentNote">
          <template v-if="editing">
            <input
              v-model="editForm.title"
              type="text"
              class="mb-3 w-full rounded border border-gray-300 px-3 py-2 text-xl font-bold text-gray-900 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
              placeholder="Note title"
            />
            <textarea
              v-model="editForm.body"
              rows="12"
              class="w-full flex-1 resize-none rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
              placeholder="Note content..."
            />
            <div class="mt-3 flex items-center gap-2">
              <button
                type="button"
                class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
                :disabled="saving || !editForm.title.trim()"
                @click="saveEdit"
              >
                {{ saving ? 'Saving…' : 'Save' }}
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                :disabled="saving"
                @click="cancelEdit"
              >
                Cancel
              </button>
            </div>
          </template>
          <template v-else>
            <h2 class="mb-2 text-xl font-bold text-gray-900">{{ currentNote.title }}</h2>
            <p class="whitespace-pre-line text-sm text-gray-700">{{ currentNote.body || 'No content.' }}</p>
          </template>
        </template>
        <div v-else class="py-16 text-center text-gray-500">
          <p>No note selected.</p>
          <p class="mt-2 text-sm">Add a note or select one from the list.</p>
        </div>
      </main>
    </div>

    <!-- Footer: Created (left), Last updated + prev/next (right) -->
    <footer class="flex shrink-0 items-center justify-between border-t border-gray-300 bg-gray-100 px-6 py-3">
      <p class="text-sm text-gray-500">
        Created: {{ currentNote ? formatDateDisplay(currentNote.created_at) : '—' }}
      </p>
      <div class="flex items-center gap-3">
        <p class="text-sm text-gray-500">
          Last updated: {{ currentNote ? formatDateDisplay(currentNote.updated_at) : '—' }}
        </p>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            aria-label="Previous note"
            :disabled="!canGoPrev"
            @click="goPrev"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <span class="min-w-[3rem] text-center text-sm text-gray-600">
            {{ currentPosition.current }} / {{ currentPosition.total }}
          </span>
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            aria-label="Next note"
            :disabled="!canGoNext"
            @click="goNext"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
    </footer>

    <!-- Delete confirmation modal -->
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
          v-if="showDeleteModal"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="delete-note-title"
          @click.self="closeDeleteModal"
        >
          <div
            class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden"
            @keydown.esc="closeDeleteModal"
          >
            <div class="flex items-center gap-3 px-6 pt-6 pb-2">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </span>
              <div>
                <h2 id="delete-note-title" class="text-lg font-semibold text-gray-900">Delete note?</h2>
                <p class="mt-0.5 text-sm text-gray-500">This note will be permanently removed. This action cannot be undone.</p>
              </div>
            </div>
            <div class="flex justify-end gap-3 px-6 pb-6 pt-4">
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#0D7377] focus:ring-offset-2 disabled:opacity-70"
                :disabled="deleting"
                @click="closeDeleteModal"
              >
                Cancel
              </button>
              <button
                type="button"
                class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-70"
                :disabled="deleting"
                @click="confirmDeleteNote"
              >
                {{ deleting ? 'Deleting…' : 'Delete' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
