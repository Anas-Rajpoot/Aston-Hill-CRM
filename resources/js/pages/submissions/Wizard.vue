<script setup>
import { ref } from 'vue'
import Step1 from '@/forms/leadSubmissionForms/Step1.vue'
import Step2 from '@/forms/leadSubmissionForms/Step2.vue'
import Step3 from '@/forms/leadSubmissionForms/Step3.vue'
import Step4 from '@/forms/leadSubmissionForms/Step4.vue'

const step = ref(1)
const leadId = ref(null)

const next = (id = null) => {
  if (id) leadId.value = id
  step.value++
}
</script>

<template>
  <div class="card">
    <Step1 v-if="step === 1" @next="next" />
    <Step2 v-if="step === 2" :leadId="leadId" @next="next" />
    <Step3 v-if="step === 3" :leadId="leadId" @next="next" />
    <Step4 v-if="step === 4" :leadId="leadId" />
  </div>
</template>
