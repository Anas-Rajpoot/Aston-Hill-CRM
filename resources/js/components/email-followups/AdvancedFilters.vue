<script setup>
/**
 * Advanced filters: search + date range with calendar picker.
 */
import { computed } from 'vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

defineEmits(['apply', 'reset'])

const activeCount = computed(() => {
  const f = props.filters
  let n = 0
  if (f.q) n++
  if (f.from || f.to) n++
  if (f.category) n++
  if (f.status) n++
  return n
})
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div v-show="visible" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="border-b border-gray-200 bg-gray-50/50 px-3 py-2">
        <h3 class="text-xs font-medium text-gray-700">
          Advanced Filters
          <span class="ml-1.5 font-normal text-gray-500">{{ activeCount }} active</span>
        </h3>
      </div>
      <div class="grid grid-cols-3 gap-x-3 gap-y-2 p-3 sm:grid-cols-4 md:grid-cols-5">
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Search</label>
          <input
            v-model="filters.q"
            type="text"
            placeholder="Subject, request from, sent to..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Email Date From</label>
          <DateInputDdMmYyyy
            v-model="filters.from"
            placeholder="dd-Mon-yyyy"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Email Date To</label>
          <DateInputDdMmYyyy
            v-model="filters.to"
            placeholder="dd-Mon-yyyy"
            :disabled="loading"
          />
        </div>
        <div class="flex items-end gap-2">
          <button
            type="button"
            class="rounded bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700"
            :disabled="loading"
            @click="$emit('apply')"
          >
            Apply
          </button>
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
            @click="$emit('reset')"
          >
            Reset
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
