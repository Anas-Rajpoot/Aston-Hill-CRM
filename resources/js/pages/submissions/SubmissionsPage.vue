<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Tabs from '@/components/Tabs.vue'

// Forms
import LeadSubmissionWizard from '@/forms/LeadSubmissionForms/LeadSubmissionWizard.vue'
import FieldSubmissionForm from '@/forms/FieldSubmissionForm/step1.vue'
import CustomerSupportForm from '@/forms/CustomerSupportForm/step1.vue'
import VasRequestWizard from '@/forms/VASRequestForm/VasRequestWizard.vue'

const route = useRoute()
const activeTab = ref('lead')
/** Incremented on every tab click so the visible form remounts (clears success message, shows fresh form). */
const formKey = ref(0)

const tabs = [
  { key: 'lead', label: 'Lead Submissions' },
  { key: 'field', label: 'Field Submissions' },
  { key: 'support', label: 'Customer Support' },
  { key: 'vas', label: 'VAS Requests' },
]

onMounted(() => {
  if (route.query.vas_request_id != null && route.query.vas_request_id !== '') {
    activeTab.value = 'vas'
  }
})

function onTabChange(key) {
  activeTab.value = key
  formKey.value = Date.now()
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6 px-4 sm:px-6">
    <div class="max-w-6xl mx-auto space-y-6">
      <!-- TABS (dark bar like 1st image) -->
      <Tabs
        :tabs="tabs"
        :active="activeTab"
        @change="onTabChange"
      />

      <!-- TAB CONTENT (white card). Key forces remount on tab click so success message clears and fresh form shows. -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <LeadSubmissionWizard v-if="activeTab === 'lead'" :key="`lead-${formKey}`" />
        <FieldSubmissionForm v-if="activeTab === 'field'" :key="`field-${formKey}`" />
        <CustomerSupportForm v-if="activeTab === 'support'" :key="`support-${formKey}`" />
        <VasRequestWizard v-if="activeTab === 'vas'" :key="`vas-${formKey}`" />
      </div>
    </div>
  </div>
</template>
