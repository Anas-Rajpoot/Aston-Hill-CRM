<script setup>
defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ statuses: [], departments: [], roles: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
    <label class="sr-only">Status</label>
    <select
      v-model="filters.status"
      class="min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Status</option>
      <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">
        {{ s.label }}
      </option>
    </select>

    <label class="sr-only">Department</label>
    <select
      v-model="filters.department"
      class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Department</option>
      <option v-for="d in filterOptions.departments" :key="d.value" :value="d.value">
        {{ d.label }}
      </option>
    </select>

    <label class="sr-only">Role</label>
    <select
      v-model="filters.role"
      class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Role</option>
      <option v-for="r in filterOptions.roles" :key="r.value" :value="r.value">
        {{ r.label }}
      </option>
    </select>

    <div class="ml-2 flex flex-wrap items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('apply')"
      >
        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Apply
      </button>
      <button
        type="button"
        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('reset')"
      >
        Reset
      </button>
    </div>
    <div class="ml-auto flex items-center gap-2">
      <slot name="after-reset" />
    </div>
  </div>
</template>
