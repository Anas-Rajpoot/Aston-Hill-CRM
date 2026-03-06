<script setup>
/**
 * Add/Edit Personal Note – layout per design: teal header (Cancel / Save), left sidebar placeholders, right panel with title + body.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import personalNotesApi from '@/services/personalNotesApi'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const title = ref('')
const body = ref('')
const loading = ref(false)
const saving = ref(false)
const loadError = ref(null)

const isEdit = computed(() => Boolean(route.params.id))
const canView = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'view', ['personal_notes.list', 'personal_notes.view'])
)
const canCreate = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'create', ['personal_notes.create', 'personal_notes.add'])
)
const canEdit = computed(() =>
  canModuleAction(auth.user, 'personal-notes', 'edit', ['personal_notes.edit', 'personal_notes.update'])
)
const canSave = computed(() => (isEdit.value ? canEdit.value : canCreate.value))

async function loadNote() {
  if (!canView.value) return
  if (!route.params.id) return
  loading.value = true
  loadError.value = null
  try {
    const note = await personalNotesApi.show(route.params.id)
    title.value = note.title ?? ''
    body.value = note.body ?? ''
  } catch {
    loadError.value = 'Could not load note.'
  } finally {
    loading.value = false
  }
}

function cancel() {
  router.push('/personal-notes')
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  try {
    if (isEdit.value) {
      await personalNotesApi.update(route.params.id, { title: title.value, body: body.value })
      router.push(`/personal-notes/${route.params.id}`)
    } else {
      const created = await personalNotesApi.create({ title: title.value, body: body.value })
      router.push(`/personal-notes/${created.id}`)
    }
  } catch {
    saving.value = false
  }
}

onMounted(() => loadNote())
</script>

<template>
  <div class="flex h-[calc(100vh-4rem)] flex-col bg-white">
    <!-- Dark teal header: Personal Notes + Cancel / Save -->
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
          type="button"
          class="rounded border-2 border-white bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70"
          :disabled="saving"
          @click="cancel"
        >
          Cancel
        </button>
        <button
          v-if="canSave"
          type="button"
          class="rounded bg-brand-primary-hover px-4 py-2 text-sm font-medium text-white shadow hover:bg-brand-primary-dark disabled:opacity-70"
          :disabled="saving"
          @click="save"
        >
          Save
        </button>
      </div>
    </header>

    <div class="flex min-h-0 flex-1">
      <!-- Left sidebar: stacked placeholders -->
      <aside class="flex w-14 shrink-0 flex-col items-center gap-2 border-r border-gray-300 bg-gray-100 py-4">
        <div
          v-for="i in 8"
          :key="i"
          class="h-10 w-8 shrink-0 rounded-md border border-gray-300 bg-gray-200/80"
          aria-hidden="true"
        />
      </aside>

      <!-- Right panel: title + body -->
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
        <div v-else-if="loadError" class="py-16 text-center text-red-600">
          {{ loadError }}
          <button type="button" class="ml-2 underline" @click="cancel">Back to notes</button>
        </div>
        <div v-else class="max-w-2xl">
          <input
            v-model="title"
            type="text"
            class="mb-4 w-full border-0 bg-transparent text-xl font-bold text-gray-900 placeholder-gray-500 focus:ring-0"
            :placeholder="isEdit ? 'Note title' : 'New Note'"
            aria-label="Note title"
          />
          <textarea
            v-model="body"
            class="min-h-[320px] w-full resize-y border-0 bg-transparent text-sm text-gray-700 placeholder-gray-500 focus:ring-0"
            :placeholder="isEdit ? 'Note content...' : 'Start writing your note...'"
            aria-label="Note content"
            rows="12"
          />
        </div>
      </main>
    </div>
  </div>
</template>
