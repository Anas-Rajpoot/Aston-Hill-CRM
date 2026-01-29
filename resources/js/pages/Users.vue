<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/services/api'
import ColumnSelector from '@/components/ColumnSelector.vue'
import Filters from '@/components/Filters.vue'
import DataTable from '@/components/DataTable.vue'

const module = 'users'

const allColumns = ref({})
const visibleColumns = ref([])
const rows = ref([])
const filters = ref({})
const sort = ref({})

const loadColumns = async () => {
  const res = await api.get(`/modules/${module}/columns`)
  allColumns.value = res.data.all_columns
  visibleColumns.value = res.data.visible_columns
}

const loadData = async () => {
  const res = await api.get(`/datatable/${module}`, {
    params: {
      columns: visibleColumns.value,
      filters: filters.value,
      sort: sort.value
    }
  })
  rows.value = res.data.data
}

onMounted(async () => {
  await loadColumns()
  await loadData()
})

watch([filters, sort, visibleColumns], loadData, { deep: true })
</script>

<template>
  <ColumnSelector
    :module="module"
    :all-columns="allColumns"
    :visible-columns="visibleColumns"
    @updated="visibleColumns = $event"
  />

  <Filters :columns="allColumns" :filters="filters" />

  <DataTable
    :rows="rows"
    :visible-columns="visibleColumns"
    :all-columns="allColumns"
    :sort="sort"
    @sort="sort = $event"
  />
</template>
