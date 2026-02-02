<script setup>
import { ref } from 'vue'
import Tabs from '@/components/Tabs.vue'

// Forms
import LeadSubmissionWizard from '@/forms/LeadSubmissionForms/LeadSubmissionWizard.vue'
import FieldSubmissionForm from '@/forms/FieldSubmissionForm/step1.vue'
import CustomerSupportForm from '@/forms/CustomerSupportForm/step1.vue'
import VASRequestForm from '@/forms/VASRequestForm/step1.vue'

const activeTab = ref('lead')

const tabs = [
  { key: 'lead', label: 'Lead Submissions' },
  { key: 'field', label: 'Field Submissions' },
  { key: 'support', label: 'Customer Support' },
  { key: 'vas', label: 'VAS Requests' },
]
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6 px-4 sm:px-6">
    <div class="max-w-6xl mx-auto space-y-6">
      <!-- TABS (dark bar like 1st image) -->
      <Tabs
        :tabs="tabs"
        :active="activeTab"
        @change="activeTab = $event"
      />

      <!-- TAB CONTENT (white card, size like 3rd image) -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <LeadSubmissionWizard v-if="activeTab === 'lead'" />
        <FieldSubmissionForm v-if="activeTab === 'field'" />
        <CustomerSupportForm v-if="activeTab === 'support'" />
        <VASRequestForm v-if="activeTab === 'vas'" />
      </div>
    </div>
  </div>
</template>
