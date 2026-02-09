<script setup>
    import { ref, computed, watch } from 'vue'
    import vasRequestsApi from '@/services/vasRequestsApi'

    const props = defineProps({
        visible: { type: Boolean, default: false },
        vasId: { type: Number, required: true },
    })

    const emit = defineEmits(['close','saved'])
    const loading = ref(false)
    const saving = ref(false)
    const executives = ref([])
    const selectedExec = ref(null)
    const notes = ref('')
    const error = ref(null)

    watch(() => props.visible, async (v) => {
        if (v) {
            loading.value = true
            try {
                const res = await vasRequestsApi.getBackOfficeOptions()
                executives.value = res.executives || []
            } catch {
                executives.value = []
            } finally { loading.value = false }
        } else {
            selectedExec.value = null
            notes.value = ''
            error.value = null
        }
    })

    const save = async () => {
        if (!selectedExec.value) {
            error.value = 'Please select an executive'
            return
        }
        saving.value = true
        try {
            await vasRequestsApi.assignBackOffice(props.vasId, {
                executive_id: selectedExec.value,
                notes: notes.value
            })
            emit('saved')
            emit('close')
        } catch(e) {
            error.value = e?.response?.data?.message || 'Failed'
        } finally { saving.value = false }
    }
</script>

<template>
  <Teleport to="body">
    <div v-if="props.visible" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-4 rounded w-96">
        <h3>Select Back Office Executive</h3>
        <select v-model="selectedExec" class="w-full my-2">
          <option value="">Select</option>
          <option v-for="ex in executives" :key="ex.id" :value="ex.id">{{ ex.name }}</option>
        </select>
        <textarea v-model="notes" placeholder="Optional notes" class="w-full border p-2"></textarea>
        <div v-if="error" class="text-red-500">{{ error }}</div>
        <div class="mt-2 flex justify-end gap-2">
          <button @click="$emit('close')">Cancel</button>
          <button @click="save" :disabled="saving" class="bg-green-600 text-white px-2 py-1 rounded">
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
