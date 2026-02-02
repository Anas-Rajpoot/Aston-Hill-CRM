<script setup>
import { ref } from 'vue'
import Step1 from './step1.vue'
import Step2 from './step2.vue'
import Step3 from './step3.vue'

const currentStep = ref(1)
const leadId = ref(null)
const submitted = ref(false)

const onStep1Next = (id) => {
  leadId.value = id
  currentStep.value = 2
}

const onStep2Back = () => {
  currentStep.value = 1
}

const onStep2Next = () => {
  currentStep.value = 3
}

const onStep3Back = () => {
  currentStep.value = 2
}

const onSubmitted = () => {
  submitted.value = true
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
      <Step1 v-if="currentStep === 1" @next="onStep1Next" />
      <Step2
        v-else-if="currentStep === 2"
        :lead-id="leadId"
        @back="onStep2Back"
        @next="onStep2Next"
      />
      <Step3
        v-else-if="currentStep === 3"
        :lead-id="leadId"
        @back="onStep3Back"
        @submitted="onSubmitted"
      />
    </template>
  </div>
</template>
