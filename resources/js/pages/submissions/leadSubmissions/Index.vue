<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/leadSubmissionsApi'
import Datatable from '@/components/datatable/Datatable.vue'
import ColumnPicker from '@/components/datatable/ColumnPicker.vue'

const columns = ref({})
const visibleColumns = ref([])

onMounted(async () => {
  const res = await api.columns()
  columns.value = res.data.columns
  visibleColumns.value = res.data.visible_columns
})

const savePrefs = async (cols) => {
  visibleColumns.value = cols
  await api.savePrefs(cols)
}
</script>

<template>
  <div class="space-y-4">
    <ColumnPicker
      :columns="columns"
      :modelValue="visibleColumns"
      @update:modelValue="savePrefs"
    />

    <Datatable
      url="/lead-submissions/datatable"
      :columns="columns"
      :visible-columns="visibleColumns"
    />
  </div>
</template>
