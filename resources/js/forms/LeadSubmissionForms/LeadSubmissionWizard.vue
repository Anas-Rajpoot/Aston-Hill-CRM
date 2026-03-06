<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Step1 from './step1.vue'
import Step4 from './step4.vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const route = useRoute()
const router = useRouter()
const emit = defineEmits(['back', 'submitted', 'new-submission'])

const props = defineProps({
  /** When true, do not restore current draft so a fresh form is shown (e.g. after "New lead submission"). */
  forceNewForm: { type: Boolean, default: false },
})

const STORAGE_KEY = 'lead_submission_wizard'

const currentStep = ref(1)
const leadId = ref(null)
const submitted = ref(false)
const isTransitioning = ref(false)
/** True only after we've restored/validated from storage (so we don't render steps with a stale leadId). */
const restoreDone = ref(false)

const MIN_TRANSITION_MS = 120

function scrollToTop() {
  nextTick(() => {
    window.scrollTo(0, 0)
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
  })
}

function saveStateToStorage() {
  if (submitted.value || !leadId.value) return
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      step: currentStep.value,
      leadId: leadId.value,
    }))
  } catch (_) {}
}

function loadStateFromStorage() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return
    const data = JSON.parse(raw)
    const step = parseInt(data.step, 10)
    const id = data.leadId != null ? parseInt(data.leadId, 10) : null
    if (!id || Number.isNaN(id)) return
    if (step >= 1 && step <= 4) {
      currentStep.value = step <= 1 ? 1 : 4
      leadId.value = id
    }
  } catch (_) {}
}

function clearStorage() {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch (_) {}
}

function resetToStep1() {
  currentStep.value = 1
  leadId.value = null
}

/** Fetch lead from API; hydrate step and step2 from DB (source of truth). Clear state if lead is submitted. */
async function validateAndHydrateFromLead() {
  const id = leadId.value
  if (!id) return null
  try {
    const res = await leadSubmissionsApi.getLead(id)
    const data = res?.data
    if (!data) return null
    if (data.status === 'submitted' || data.status === 'approved' || data.status === 'rejected') {
      clearStorage()
      resetToStep1()
      return null
    }
    const hydratedStep = Math.min(4, Math.max(1, parseInt(data.step, 10) || 1))
    currentStep.value = hydratedStep <= 1 ? 1 : 4
    return data
  } catch (e) {
    if (e?.response?.status === 404) {
      clearStorage()
      resetToStep1()
    }
    return null
  }
}

/** On load: restore leadId from storage, then fetch lead from API and set step + step2 from DB. If no leadId, try current draft. Skip draft when forceNewForm (new submission). */
async function restoreFromCurrentDraft() {
  if (props.forceNewForm) {
    restoreDone.value = true
    return
  }
  loadStateFromStorage()
  if (leadId.value) {
    await validateAndHydrateFromLead()
  } else {
    try {
      const res = await leadSubmissionsApi.getCurrentDraft()
      const draft = res?.data?.draft
      if (draft?.id && draft?.status === 'draft') {
        leadId.value = draft.id
        const draftStep = Math.min(4, Math.max(1, parseInt(draft.step, 10) || 1))
        currentStep.value = draftStep <= 1 ? 1 : 4
      }
    } catch (_) {}
  }
  saveStateToStorage()
  restoreDone.value = true
}

function afterStepChange() {
  scrollToTop()
  const start = Date.now()
  nextTick(() => {
    const elapsed = Date.now() - start
    const delay = Math.max(0, MIN_TRANSITION_MS - elapsed)
    setTimeout(() => { isTransitioning.value = false }, delay)
  })
}

onMounted(async () => {
  if (route.query.step || route.query.lead_id) {
    const q = { ...route.query }
    delete q.step
    delete q.lead_id
    router.replace({ path: route.path, query: Object.keys(q).length ? q : {} })
  }
  await restoreFromCurrentDraft()
  scrollToTop()
})

watch(
  [currentStep, leadId],
  () => { saveStateToStorage() },
  { deep: true }
)

const onStep1Next = (id) => {
  isTransitioning.value = true
  leadId.value = id
  currentStep.value = 4
  afterStepChange()
}

const onStep1DraftSaved = (id) => {
  if (id != null) leadId.value = id
}

const onStep4Back = () => {
  isTransitioning.value = true
  currentStep.value = 1
  afterStepChange()
}

const onSubmitted = () => {
  submitted.value = true
  leadId.value = null
  currentStep.value = 1
  clearStorage()
  if (route.query.step || route.query.lead_id) {
    router.replace({ path: route.path })
  }
  scrollToTop()
}

/** From success screen: open a new submission form. Emit so parent remounts wizard for a clean form. */
function startNewSubmission() {
  clearStorage()
  submitted.value = false
  resetToStep1()
  restoreDone.value = true
  emit('new-submission')
  scrollToTop()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success message: after submit, show this; New lead submission button and tab open fresh form -->
    <div v-if="submitted" class="rounded-lg bg-brand-primary-light border border-brand-primary-muted p-6 text-center">
      <svg class="mx-auto w-12 h-12 text-brand-primary mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-brand-primary-hover">Lead submission completed</h3>
      <p class="mt-1 text-sm text-brand-primary">Your lead has been submitted successfully. Back Office will review and process accordingly.</p>
      <button
        type="button"
        @click="startNewSubmission"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
      >
        New lead submission
      </button>
    </div>

    <template v-else>
      <div class="relative min-h-[320px]">
        <!-- Restore/validation: avoid mounting steps with stale leadId until we've validated storage -->
        <div
          v-if="!restoreDone"
          class="flex flex-col items-center justify-center rounded-xl bg-gray-50 border border-gray-200 py-16 px-6 min-h-[320px]"
          aria-live="polite"
          aria-busy="true"
        >
          <svg class="animate-spin h-10 w-10 text-brand-primary mb-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <p class="text-sm font-medium text-gray-700">Loading...</p>
        </div>
        <!-- Rendering overlay: show instead of empty page while step is switching -->
        <div
          v-else-if="isTransitioning"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-xl bg-gray-50 border border-gray-200 py-16 px-6"
          aria-live="polite"
          aria-busy="true"
        >
          <svg class="animate-spin h-10 w-10 text-brand-primary mb-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <p class="text-sm font-medium text-gray-700">Rendering...</p>
        </div>

        <template v-else>
        <Step1
          v-if="currentStep === 1"
          :lead-id="leadId"
          :skip-load-draft="props.forceNewForm"
          @next="onStep1Next"
          @draft-saved="onStep1DraftSaved"
        />
        <Step4
          v-else-if="currentStep === 4"
          :lead-id="leadId"
          @back="onStep4Back"
          @submitted="onSubmitted"
        />
        </template>
      </div>
    </template>
  </div>
</template>
