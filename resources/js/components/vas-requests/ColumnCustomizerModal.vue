<script setup>
    import { ref, watch } from 'vue'

    const props = defineProps({
        visible: Boolean,
        allColumns: Array,
        selectedColumns: Array,
    })

    const emit = defineEmits(['update:visible','save'])
    const localSelected = ref([...props.selectedColumns])
    const error = ref('')

    watch(() => [props.visible, props.selectedColumns], ([v, sel]) => {
        if (v) localSelected.value = [...sel]
    })

    const toggle = (col) => {
        const i = localSelected.value.indexOf(col.key)
        if (i >= 0) localSelected.value.splice(i,1)
        else localSelected.value.push(col.key)
    }

    const save = () => {
        if(localSelected.value.length < 4){
            error.value = 'Select at least 4 columns'
            return
        }
        emit('save', localSelected.value)
        emit('update:visible', false)
    }
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 bg-black/50 flex justify-center items-start pt-16">
      <div class="bg-white p-4 rounded w-80">
        <h3>Customize Columns</h3>
        <div class="space-y-1 mt-2">
          <label v-for="col in allColumns" :key="col.key" class="flex items-center gap-2">
            <input type="checkbox" :checked="localSelected.includes(col.key)" @change="()=>toggle(col)" />
            {{ col.label }}
          </label>
        </div>
        <p v-if="error" class="text-red-500">{{ error }}</p>
        <div class="mt-2 flex justify-end gap-2">
          <button @click="save" class="bg-green-600 text-white px-2 py-1 rounded">Save</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
