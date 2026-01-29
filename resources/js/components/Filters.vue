<script setup>
const props = defineProps({
  columns: Object,
  filters: Object
})
</script>

<template>
  <div>
    <div v-for="(col, key) in columns" :key="key">
      <input v-if="col.filter === 'text'" v-model="filters[key]" />

      <select v-if="col.filter === 'select'" v-model="filters[key]">
        <option value="">All</option>
        <option v-for="o in col.options" :key="o">{{ o }}</option>
      </select>
    </div>
  </div>
</template>
