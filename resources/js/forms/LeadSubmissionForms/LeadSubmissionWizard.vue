<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Step1 from './step1.vue'
import Step2 from './step2.vue'
import Step3 from './step3.vue'

const route = useRoute()
const router = useRouter()

const currentStep = ref(1)
const leadId = ref(null)
const submitted = ref(false)
const step2CategoryId = ref(null)
const step2TypeId = ref(null)
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
  const rawId = route.query.lead_id
  const id = rawId != null && rawId !== '' ? parseInt(rawId, 10) : null
  const validId = id != null && !Number.isNaN(id)
  if ((step === 2 || step === 3) && validId) {
    currentStep.value = step
    leadId.value = id
    return
  }
  currentStep.value = 1
  leadId.value = validId ? id : null
}

function syncUrlToStep() {
  if (submitted.value) return
  const step = currentStep.value
  const id = leadId.value
  const query = {}
  if (step > 1) query.step = step
  if (id) query.lead_id = id
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
  () => [route.query.step, route.query.lead_id],
  () => { readStepFromRoute() },
  { immediate: false }
)

const onStep1Next = (id) => {
  isTransitioning.value = true
  leadId.value = id
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

const onStep2Next = (categoryId, typeId) => {
  isTransitioning.value = true
  step2CategoryId.value = categoryId
  step2TypeId.value = typeId
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

const onSubmitted = () => {
  submitted.value = true
  router.replace({ path: route.path })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success message -->
    <div v-if="submitted" class="rounded-lg bg-green-50 border border-green-200 p-6 text-center">
      <svg class="mx-auto w-12 h-12 text-green-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-green-800">Lead Submission Submitted</h3>
      <p class="mt-1 text-sm text-green-600">Your lead submission has been submitted successfully.</p>
    </div>

    <template v-else>
      <div class="relative min-h-[320px]">
        <!-- Rendering overlay: show instead of empty page while step is switching -->
        <div
          v-if="isTransitioning"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-xl bg-gray-50 border border-gray-200 py-16 px-6"
          aria-live="polite"
          aria-busy="true"
        >
          <svg class="animate-spin h-10 w-10 text-green-500 mb-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <p class="text-sm font-medium text-gray-700">Rendering...</p>
        </div>

        <Step1 v-if="currentStep === 1" :lead-id="leadId" @next="onStep1Next" />
        <Step2
          v-else-if="currentStep === 2"
          :lead-id="leadId"
          :initial-category-id="step2CategoryId"
          :initial-type-id="step2TypeId"
          @back="onStep2Back"
          @next="onStep2Next"
        />
        <Step3
          v-else-if="currentStep === 3"
          :lead-id="leadId"
          @back="onStep3Back"
          @submitted="onSubmitted"
        />
      </div>
    </template>
  </div>
</template>
