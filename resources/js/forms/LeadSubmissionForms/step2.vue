<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  leadId: { type: Number, required: true },
  initialCategoryId: { type: [Number, String], default: null },
  initialTypeId: { type: [Number, String], default: null },
})

const emit = defineEmits(['next', 'back'])

const router = useRouter()
const categories = ref([])
const serviceTypes = ref([])
const selectedCategoryId = ref(props.initialCategoryId || '')
const selectedTypeId = ref(props.initialTypeId || '')
const loading = ref(true)
const saving = ref(false)
const savingDraft = ref(false)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

// Category descriptions (when not in DB)
const categoryDescriptions = {
  fixed: 'Fixed line services and internet connectivity',
  fms: 'Facilities Management Services',
  gsm: 'Mobile and GSM services',
  other: 'Other services and requests',
}

const getCategoryDescription = (cat) => {
  const slug = (cat.slug || cat.name || '').toLowerCase()
  return categoryDescriptions[slug] || ''
}

// Icons per category slug
const categoryIcons = {
  fixed: 'wifi',
  fms: 'building',
  gsm: 'mobile',
  other: 'dots',
}

const getCategoryIcon = (cat) => {
  const slug = (cat.slug || cat.name || '').toLowerCase()
  return categoryIcons[slug] || 'dots'
}

const selectedCategory = computed(() =>
  categories.value.find((c) => String(c.id) === String(selectedCategoryId.value))
)

onMounted(async () => {
  loading.value = true
  try {
    const [catsRes, leadRes] = await Promise.all([
      api.getCategories(),
      api.getLead(props.leadId).catch(() => ({ data: null })),
    ])
    categories.value = catsRes.data || []

    const lead = leadRes?.data
    const catId = props.initialCategoryId ?? lead?.service_category_id
    const typeId = props.initialTypeId ?? lead?.service_type_id

    if (catId) {
      selectedCategoryId.value = catId
      await fetchServiceTypes(catId)
      selectedTypeId.value = typeId || ''
    }
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

watch(selectedCategoryId, async (newId) => {
  selectedTypeId.value = ''
  serviceTypes.value = []
  if (newId) {
    await fetchServiceTypes(newId)
  }
})

const fetchServiceTypes = async (categoryId) => {
  if (!categoryId) return
  try {
    const { data } = await api.getServiceTypesByCategory(categoryId)
    serviceTypes.value = data || []
  } catch (_) {
    serviceTypes.value = []
  }
}

const selectCategory = (id) => {
  selectedCategoryId.value = id
  clearFieldError('service_category_id')
  clearFieldError('service_type_id')
}

const inputClass = (field) => {
  const hasError = errors.value && errors.value[field]
  return `w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 ${hasError ? 'border-red-500' : 'border-gray-300'}`
}

const saveDraft = async () => {
  if (!selectedCategoryId.value || !selectedTypeId.value) {
    setErrors({ service_type_id: ['Please select both category and service type.'] })
    return
  }
  clearErrors()
  savingDraft.value = true
  try {
    await api.storeStep2(props.leadId, {
      service_category_id: selectedCategoryId.value,
      service_type_id: selectedTypeId.value,
    })
  } catch (e) {
    setErrors(e)
  } finally {
    savingDraft.value = false
  }
}

const submit = async () => {
  clearErrors()
  if (!selectedCategoryId.value || !selectedTypeId.value) {
    setErrors({ service_type_id: ['Please select both category and service type.'] })
    return
  }
  saving.value = true
  try {
    await api.storeStep2(props.leadId, {
      service_category_id: selectedCategoryId.value,
      service_type_id: selectedTypeId.value,
    })
    emit('next')
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

const goBack = () => emit('back')
const cancel = () => router.push('/submissions')
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center py-12">
    <svg class="animate-spin h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
  </div>

  <div v-else class="space-y-8">
    <!-- Validation errors summary -->
    <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg bg-red-50 border border-red-200 p-4">
      <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      <ul v-if="Object.keys(errors).length > 0" class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
        <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
      </ul>
    </div>

    <!-- Service Category -->
    <div>
      <h3 class="text-base font-semibold text-gray-900 mb-2">Service Category</h3>
      <p class="text-sm text-gray-500 mb-4">Please select the service category for this submission.</p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <button
          v-for="cat in categories"
          :key="cat.id"
          type="button"
          @click="selectCategory(cat.id)"
          class="flex items-start gap-4 p-4 rounded-xl border-2 text-left transition"
          :class="selectedCategoryId === cat.id
            ? 'border-blue-500 bg-blue-50'
            : 'border-gray-200 bg-white hover:border-gray-300'"
        >
          <div
            class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
            :class="selectedCategoryId === cat.id ? 'bg-blue-100' : 'bg-gray-100'"
          >
            <!-- Fixed: wifi -->
            <svg v-if="getCategoryIcon(cat) === 'wifi'" class="w-6 h-6" :class="selectedCategoryId === cat.id ? 'text-blue-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
            </svg>
            <!-- FMS: building -->
            <svg v-else-if="getCategoryIcon(cat) === 'building'" class="w-6 h-6" :class="selectedCategoryId === cat.id ? 'text-blue-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <!-- GSM: mobile -->
            <svg v-else-if="getCategoryIcon(cat) === 'mobile'" class="w-6 h-6" :class="selectedCategoryId === cat.id ? 'text-blue-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <!-- Other: dots -->
            <svg v-else class="w-6 h-6" :class="selectedCategoryId === cat.id ? 'text-blue-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="font-medium" :class="selectedCategoryId === cat.id ? 'text-blue-700' : 'text-gray-800'">
              {{ cat.name }}
              <svg v-if="selectedCategoryId === cat.id" class="inline w-5 h-5 ml-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </p>
            <p class="text-sm text-gray-500 mt-0.5">{{ getCategoryDescription(cat) }}</p>
          </div>
        </button>
      </div>

      <!-- Confirmation banner when category selected -->
      <div
        v-if="selectedCategoryId"
        class="mt-4 rounded-lg bg-green-50 border border-green-200 p-4 flex items-center gap-3"
      >
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm font-medium text-green-800">
          {{ selectedCategory?.name }} category selected. {{ serviceTypes.length ? 'Select Service Type.' : 'Loading types...' }}
        </p>
      </div>
    </div>

    <!-- Service Type (dynamic based on category) -->
    <div v-if="selectedCategoryId" class="pt-4 border-t border-gray-200">
      <h3 class="text-base font-semibold text-gray-900 mb-2">Service Type</h3>
      <p class="text-sm text-gray-500 mb-4">Select the service type for <strong>{{ selectedCategory?.name }}</strong> category.</p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label
          v-for="type in serviceTypes"
          :key="type.id"
          class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-green-300 transition"
          :class="{ 'border-green-500 bg-green-50': selectedTypeId === type.id }"
        >
          <input
            v-model="selectedTypeId"
            type="radio"
            :value="type.id"
            class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500"
            @change="clearFieldError('service_type_id')"
          />
          <span class="font-medium text-gray-800">{{ type.name }}</span>
        </label>
      </div>

      <p v-if="getError('service_type_id') || getError('service_category_id')" class="mt-2 text-sm text-red-600">{{ getError('service_type_id') || getError('service_category_id') }}</p>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap items-center justify-end gap-3 pt-6 border-t border-gray-200">
      <button
        type="button"
        @click="goBack"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back
      </button>
      <button
        type="button"
        @click="cancel"
        class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
      >
        Cancel
      </button>
      <span class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium">Step 2</span>
      <button
        type="button"
        :disabled="savingDraft || !selectedCategoryId || !selectedTypeId"
        @click="saveDraft"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 disabled:opacity-50"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
        </svg>
        {{ savingDraft ? 'Saving...' : 'Save as Draft' }}
      </button>
      <button
        type="button"
        :disabled="saving || !selectedCategoryId || !selectedTypeId"
        @click="submit"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-500 text-white text-sm font-medium hover:bg-green-600 disabled:opacity-50"
      >
        Next
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>
      </button>
    </div>
  </div>
</template>
