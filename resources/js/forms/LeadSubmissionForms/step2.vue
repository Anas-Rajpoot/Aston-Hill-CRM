<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  leadId: Number,
  initialCategoryId: [Number, String]
})

const emit = defineEmits(['next', 'back'])

const categories = ref([])
const form = ref({
  service_category_id: props.initialCategoryId || ''
})

const { errors, setErrors, clearErrors } = useFormErrors()

onMounted(async () => {
  const { data } = await api.getCategories()
  categories.value = data
})

const submit = async () => {
  clearErrors()
  try {
    await api.storeStep2(props.leadId, form.value)
    emit('next')
  } catch (e) {
    setErrors(e)
  }
}
</script>

<template>
  <div class="space-y-4">
    <WizardSteps :step="2" />

    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">

      <Select
        label="Service Category"
        placeholder="Select Category"
        v-model="form.service_category_id"
        :options="categories"
        option-label="name"
        option-value="id"
        :error="errors.service_category_id"
      />

      <div class="md:col-span-2 flex justify-between mt-2">
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
