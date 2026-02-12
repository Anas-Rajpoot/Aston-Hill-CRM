<script setup>
/**
 * Attendance Log filters bar – Employee, Role only; Apply, Reset; slot for Advanced Filters + Customize Columns.
 * From, To, Status are in Advanced Filters (see AttendanceLogPage).
 */
defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ users: [], roles: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
  <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
    <div>
      <label class="block text-xs font-medium text-gray-600">Employee</label>
      <select
        v-model="filters.user_id"
        class="mt-0.5 min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">All</option>
        <option v-for="u in filterOptions.users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600">Role</label>
      <select
        v-model="filters.role"
        class="mt-0.5 min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">All</option>
        <option v-for="r in filterOptions.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
    </div>
    <div class="flex flex-wrap items-center gap-2">
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
