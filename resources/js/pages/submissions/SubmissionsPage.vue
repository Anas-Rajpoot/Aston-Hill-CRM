<script setup>
import { ref } from 'vue'
import StatsCards from '@/components/StatsCards.vue'
import Tabs from '@/components/Tabs.vue'

// Forms
import LeadSubmissionWizard from '@/forms/LeadSubmissionForms/LeadSubmissionWizard.vue'
import FieldSubmissionForm from '@/forms/FieldSubmissionForm/step1.vue'
import CustomerSupportForm from '@/forms/CustomerSupportForm/step1.vue'
import VASRequestForm from '@/forms/VASRequestForm/step1.vue'

const activeTab = ref('lead')

const tabs = [
  { key: 'lead', label: 'Lead Submissions', count: 20 },
  { key: 'field', label: 'Field Submissions', count: 12 },
  { key: 'support', label: 'Customer Support', count: 12 },
  { key: 'vas', label: 'VAS Requests', count: 15 },
]
</script>

<template>
  <div class="space-y-6">

    <!-- STATS -->
    <StatsCards />

    <!-- TABS -->
    <Tabs
      :tabs="tabs"
      :active="activeTab"
      @change="activeTab = $event"
    />

    <!-- TAB CONTENT -->
    <div class="bg-white rounded-xl shadow p-6">
      <LeadSubmissionWizard v-if="activeTab === 'lead'" />
      <FieldSubmissionForm v-if="activeTab === 'field'" />
      <CustomerSupportForm v-if="activeTab === 'support'" />
      <VASRequestForm v-if="activeTab === 'vas'" />
    </div>

  </div>
</template>
