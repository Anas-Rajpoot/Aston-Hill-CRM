<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Step1 from './step1.vue'

const route = useRoute()
const router = useRouter()

const vasRequestId = ref(null)
const submitted = ref(false)
const draftSavedMessage = ref('')

function scrollToTop() {
  nextTick(() => {
    window.scrollTo(0, 0)
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
  })
}

function readStepFromRoute() {
  const rawId = route.query.vas_request_id
  const id = rawId != null && rawId !== '' ? parseInt(rawId, 10) : null
  const validId = id != null && !Number.isNaN(id)
  vasRequestId.value = validId ? id : null
}

function syncUrl() {
  if (submitted.value) return
  const id = vasRequestId.value
  const query = {}
  if (id) query.vas_request_id = id
  if (Object.keys(query).length && JSON.stringify(route.query) !== JSON.stringify(query)) {
    router.replace({ path: route.path, query })
  }
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

const onDraftSaved = () => {
  draftSavedMessage.value = 'Draft saved.'
  scrollToTop()
  setTimeout(() => { draftSavedMessage.value = '' }, 3000)
}

const onStep1Submitted = () => {
  submitted.value = true
  draftSavedMessage.value = ''
  router.replace({ path: route.path })
  scrollToTop()
}

const startNewRequest = () => {
  submitted.value = false
  vasRequestId.value = null
  draftSavedMessage.value = ''
  router.replace({ path: route.path })
  scrollToTop()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success message -->
    <div v-if="submitted" class="rounded-lg border border-brand-primary-muted bg-brand-primary-light p-6 text-center">
      <svg class="mx-auto mb-3 h-12 w-12 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-brand-primary-hover">VAS submission completed</h3>
      <p class="mt-1 text-sm text-brand-primary">Your VAS request has been submitted successfully. Back Office will review and process accordingly.</p>
      <p class="mt-3 text-sm text-gray-600">Click the button below to start a new VAS request.</p>
      <button
        type="button"
        @click="startNewRequest"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
      >
        New VAS Request
      </button>
    </div>

    <template v-else>
      <div v-if="draftSavedMessage" class="rounded-lg border border-brand-primary-muted bg-brand-primary-light px-4 py-2 text-sm text-brand-primary-hover">
        {{ draftSavedMessage }}
      </div>

      <div class="relative min-h-[320px]">
        <Step1
          :vas-request-id="vasRequestId"
          @draft-saved="onDraftSaved"
          @submitted="onStep1Submitted"
        />
      </div>
    </template>
  </div>
</template>
