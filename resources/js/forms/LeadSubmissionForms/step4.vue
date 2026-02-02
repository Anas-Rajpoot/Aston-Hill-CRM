<script setup>
import { ref } from 'vue'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  leadId: Number,
  docDefs: Array
})

const files = ref({})
const progress = ref(0)

const { errors, setErrors, clearErrors } = useFormErrors()

const onFileChange = (key, e) => {
  files.value[key] = e.target.files[0]
}

const submit = async (action) => {
  clearErrors()

  const fd = new FormData()
  fd.append('action', action)

  Object.entries(files.value).forEach(([key, file]) => {
    fd.append(`documents[${key}]`, file)
  })

  try {
    await api.storeStep4(props.leadId, fd, {
      onUploadProgress(e) {
        progress.value = Math.round((e.loaded * 100) / e.total)
      }
    })
  } catch (e) {
    setErrors(e)
  }
}
</script>

<template>
  <div class="space-y-4">
    <WizardSteps :step="4" />

    <form @submit.prevent class="space-y-4">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="doc in docDefs" :key="doc.key">
          <label class="text-xs font-medium text-gray-600">
            {{ doc.label }}
            <span v-if="doc.required" class="text-red-600">*</span>
          </label>

          <input
            type="file"
            class="w-full rounded-md border-gray-300"
            @change="e => onFileChange(doc.key, e)"
          />

          <p v-if="errors[`documents.${doc.key}`]" class="text-xs text-red-600">
            {{ errors[`documents.${doc.key}`][0] }}
          </p>
        </div>
      </div>

      <progress v-if="progress" :value="progress" max="100" />

      <div class="flex justify-end gap-2">
        <button class="btn-dark" @click="submit('save')">
          Save
        </button>
        <button class="btn-primary inline-flex items-center gap-2" @click="submit('submit')">
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126a59.768 59.768 0 0118.216-8.268 59.768 59.768 0 0118.216 8.268L18 12m-6 0h7.5" />
          </svg>
          Submit Lead Submission
        </button>
      </div>
    </form>
  </div>
</template>
