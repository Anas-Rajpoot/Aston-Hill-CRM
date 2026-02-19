<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Step1 from './step1.vue'
import Step2 from './step2.vue'
import Step3 from './step3.vue'

const route = useRoute()
const router = useRouter()

const currentStep = ref(1)
const vasRequestId = ref(null)
const submitted = ref(false)
const draftSavedMessage = ref('')
const isTransitioning = ref(false)

const MIN_TRANSITION_MS = 120

function scrollToTop() {
  nextTick(() => {
    window.scrollTo(0, 0)
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
  })
}

function readStepFromRoute() {
  const step = parseInt(route.query.step, 10)
  const rawId = route.query.vas_request_id
  const id = rawId != null && rawId !== '' ? parseInt(rawId, 10) : null
  const validId = id != null && !Number.isNaN(id)
  if ((step === 2 || step === 3) && validId) {
    currentStep.value = step
    vasRequestId.value = id
    return
  }
  currentStep.value = 1
  vasRequestId.value = validId ? id : null
}

function syncUrlToStep() {
  if (submitted.value) return
  const step = currentStep.value
  const id = vasRequestId.value
  const query = {}
  if (step > 1) query.step = step
  if (id) query.vas_request_id = id
  if (Object.keys(query).length && JSON.stringify(route.query) !== JSON.stringify(query)) {
    router.replace({ path: route.path, query })
  }
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

onMounted(() => {
  readStepFromRoute()
  scrollToTop()
})

watch(
  () => [route.query.step, route.query.vas_request_id],
  () => { readStepFromRoute() },
  { immediate: false }
)

const onStep1Next = (id) => {
  isTransitioning.value = true
  vasRequestId.value = id
  currentStep.value = 2
  syncUrlToStep()
  afterStepChange()
}

const onStep2Back = () => {
  isTransitioning.value = true
  currentStep.value = 1
  syncUrlToStep()
  afterStepChange()
}

const onStep2Next = () => {
  isTransitioning.value = true
  currentStep.value = 3
  syncUrlToStep()
  afterStepChange()
}

const onStep3Back = () => {
  isTransitioning.value = true
  currentStep.value = 2
  syncUrlToStep()
  afterStepChange()
}

const onDraftSaved = () => {
  draftSavedMessage.value = 'Draft saved.'
  scrollToTop()
  setTimeout(() => { draftSavedMessage.value = '' }, 3000)
}

const startNewRequest = () => {
  submitted.value = false
  vasRequestId.value = null
  currentStep.value = 1
  draftSavedMessage.value = ''
  router.replace({ path: route.path })
  scrollToTop()
}

const onSubmitted = () => {
  submitted.value = true
  draftSavedMessage.value = ''
  router.replace({ path: route.path })
  scrollToTop()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success message -->
    <div v-if="submitted" class="rounded-lg border border-green-200 bg-green-50 p-6 text-center">
      <svg class="mx-auto mb-3 h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-green-800">VAS Request Submitted</h3>
      <p class="mt-1 text-sm text-green-600">Your VAS request has been submitted successfully.</p>
      <p class="mt-3 text-sm text-gray-600">Click the button below to start a new VAS request.</p>
      <button
        type="button"
        @click="startNewRequest"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700"
      >
        New VAS Request
      </button>
    </div>

    <template v-else>
      <div v-if="draftSavedMessage" class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
        {{ draftSavedMessage }}
      </div>

      <div class="relative min-h-[320px]">
        <div
          v-if="isTransitioning"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-gray-50 py-16 px-6"
          aria-live="polite"
          aria-busy="true"
        >
          <svg class="mb-4 h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <p class="text-sm font-medium text-gray-700">Rendering...</p>
        </div>

        <Step1 v-if="currentStep === 1" :vas-request-id="vasRequestId" @next="onStep1Next" />
        <Step2
          v-else-if="currentStep === 2"
          :vas-request-id="vasRequestId"
          @back="onStep2Back"
          @draft-saved="onDraftSaved"
          @next="onStep2Next"
        />
        <Step3
          v-else-if="currentStep === 3"
          :vas-request-id="vasRequestId"
          @back="onStep3Back"
          @draft-saved="onDraftSaved"
          @submitted="onSubmitted"
        />
      </div>
    </template>
  </div>
</template>
