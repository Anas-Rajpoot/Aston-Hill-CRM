<script setup>
const props = defineProps({
  rows: Array,
  visibleColumns: Array,
  allColumns: Object,
  sort: Object
})

const emit = defineEmits(['sort'])

const toggleSort = (col) => {
  if (props.sort.column === col) {
    emit('sort', {
      column: col,
      direction: props.sort.direction === 'asc' ? 'desc' : 'asc'
    })
  } else {
    emit('sort', { column: col, direction: 'asc' })
  }
}
</script>

<template>
  <table>
    <thead>
      <tr>
        <th
          v-for="col in visibleColumns"
          :key="col"
          @click="toggleSort(col)"
        >
          {{ allColumns[col].label }}
          <span v-if="sort.column === col">
            {{ sort.direction === 'asc' ? '▲' : '▼' }}
          </span>
        </th>
      </tr>
    </thead>

    <tbody>
      <tr v-for="row in rows" :key="row.id">
        <td v-for="col in visibleColumns" :key="col">
          {{ row[col] }}
        </td>
      </tr>
    </tbody>
  </table>
</template>
