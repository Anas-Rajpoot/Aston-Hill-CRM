<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '@/services/leadSubmissionsApi'
import SchemaRenderer from '@/components/schema/SchemaRenderer.vue'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  leadId: Number,
  initialTypeId: [Number, String]
})

const emit = defineEmits(['next', 'back'])

const types = ref([])
const fields = ref([])

const form = ref({
  service_type_id: props.initialTypeId || '',
  meta: {}
})

const { errors, setErrors, clearErrors } = useFormErrors()

onMounted(async () => {
  const { data } = await api.getServiceTypes()
  types.value = data
})

watch(() => form.value.service_type_id, async (id) => {
  if (!id) {
    fields.value = []
    return
  }
  const { data } = await api.getTypeSchema(id)
  fields.value = data.fields
})

const submit = async () => {
  clearErrors()
  try {
    await api.storeStep3(props.leadId, form.value)
    emit('next')
  } catch (e) {
    setErrors(e)
  }
}
</script>

<template>
  <div class="space-y-4">
    <WizardSteps :step="3" />

    <form @submit.prevent="submit" class="space-y-4">

      <Select
        label="Service Type"
        placeholder="Select Service Type"
        v-model="form.service_type_id"
        :options="types"
        option-label="name"
        option-value="id"
        :error="errors.service_type_id"
      />

      <div
        v-if="!form.service_type_id"
        class="p-4 rounded-lg border bg-gray-50 text-sm text-gray-600"
      >
        Please select Service Type to load dynamic fields.
      </div>

      <SchemaRenderer
        v-if="fields.length"
        :fields="fields"
        v-model="form.meta"
        :errors="errors"
      />

      <div class="flex justify-between mt-2">
        <button type="button" class="btn-secondary" @click="emit('back')">
          Back
        </button>
        <button class="btn-primary">
          Continue
        </button>
      </div>
    </form>
  </div>
</template>
