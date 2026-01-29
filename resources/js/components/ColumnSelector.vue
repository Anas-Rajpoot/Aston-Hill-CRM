<script setup>
import { ref } from 'vue'
import draggable from 'vuedraggable'
import api from '@/services/api'

const props = defineProps({
  module: String,
  allColumns: Object,
  visibleColumns: Array
})

const emit = defineEmits(['updated'])
const selected = ref([...props.visibleColumns])

const save = async () => {
  await api.post(`/modules/${props.module}/columns`, {
    columns: selected.value
  })
  emit('updated', selected.value)
}
</script>

<template>
  <button @click="$refs.modal.show()">Customize Columns</button>

  <dialog ref="modal">
    <draggable v-model="selected">
      <div v-for="col in selected" :key="col">
        <input type="checkbox" :value="col" v-model="selected" />
        {{ allColumns[col].label }}
      </div>
    </draggable>

    <button @click="save">Save</button>
  </dialog>
</template>
