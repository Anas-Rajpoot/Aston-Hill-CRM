<script setup>
import { ref, watch } from 'vue'
import axios from '@/lib/axios'

const props = defineProps({
  url: String,
  columns: Object,
  visibleColumns: Array,
})

const rows = ref([])
const loading = ref(false)

const load = async () => {
  loading.value = true
  const { data } = await axios.post(props.url)
  rows.value = data.data
  loading.value = false
}

watch(() => props.visibleColumns, load, { immediate: true })
</script>

<template>
  <table class="w-full text-sm">
    <thead>
      <th v-for="c in visibleColumns" :key="c">
        {{ columns[c].label }}
      </th>
    </thead>

    <tbody>
      <tr v-for="r in rows" :key="r.id">
        <td v-for="c in visibleColumns" :key="c">
          {{ r[c] }}
        </td>
      </tr>
    </tbody>
  </table>
</template>
