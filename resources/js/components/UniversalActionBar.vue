<script setup>
/**
 * UniversalActionBar — Sticky horizontal bar at the top of every listing page.
 * Contains: Create button, Search, Filters, Advanced Filters, Column Customization, Export.
 * All slots are optional so each page provides only what it needs.
 */
import { ref } from 'vue'

defineProps({
  /** Page title shown in the action bar */
  title: { type: String, default: '' },
  /** Show search input */
  searchable: { type: Boolean, default: true },
  /** Search placeholder */
  searchPlaceholder: { type: String, default: 'Search...' },
  /** Current search value (v-model) */
  modelValue: { type: String, default: '' },
  /** Loading state */
  loading: { type: Boolean, default: false },
  /** Number of selected items (for bulk actions) */
  selectedCount: { type: Number, default: 0 },
})

defineEmits(['update:modelValue', 'search', 'clear-selection'])

const showAdvancedFilters = ref(false)
</script>

<template>
  <div class="action-bar sticky top-0 z-20 bg-white border-b border-gray-200 shadow-sm">
    <div class="flex items-center gap-2 px-3 py-2 min-h-[48px] overflow-x-auto">

      <!-- Title (optional) -->
      <h1 v-if="title" class="text-sm font-semibold text-gray-800 whitespace-nowrap mr-1">
        {{ title }}
      </h1>

      <!-- Create button slot -->
      <slot name="create" />

      <!-- Search -->
      <div v-if="searchable" class="relative flex-shrink-0 w-52">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          type="text"
          :value="modelValue"
          :placeholder="searchPlaceholder"
          class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-brand-primary focus:border-brand-primary bg-gray-50 placeholder-gray-400"
          @input="$emit('update:modelValue', $event.target.value)"
          @keydown.enter="$emit('search')"
        />
      </div>

      <!-- Filters slot (inline filter dropdowns) -->
      <slot name="filters" />

      <!-- Separator -->
      <div class="flex-1" />

      <!-- Bulk actions (shown when items selected) -->
      <div v-if="selectedCount > 0" class="flex items-center gap-2 flex-shrink-0">
        <span class="text-xs font-medium text-brand-primary bg-brand-primary/10 px-2 py-1 rounded-md whitespace-nowrap">
          {{ selectedCount }} selected
        </span>
        <slot name="bulk-actions" />
        <button
          type="button"
          class="text-xs text-gray-500 hover:text-gray-700 underline"
          @click="$emit('clear-selection')"
        >Clear</button>
      </div>

      <!-- Right-side actions -->
      <div class="flex items-center gap-1.5 flex-shrink-0">
        <!-- Advanced filters toggle -->
        <slot name="advanced-filters-toggle">
          <button
            v-if="$slots['advanced-filters']"
            type="button"
            class="action-btn"
            :class="showAdvancedFilters ? 'bg-brand-primary/10 text-brand-primary' : ''"
            title="Advanced Filters"
            @click="showAdvancedFilters = !showAdvancedFilters"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <span class="hidden sm:inline">Filters</span>
          </button>
        </slot>

        <!-- Column customizer slot -->
        <slot name="columns" />

        <!-- Import slot -->
        <slot name="import" />

        <!-- Export slot -->
        <slot name="export" />

        <!-- Extra actions -->
        <slot name="actions" />
      </div>
    </div>

    <!-- Advanced filters panel (expandable) -->
    <div v-if="showAdvancedFilters && $slots['advanced-filters']" class="border-t border-gray-100 px-3 py-2 bg-gray-50/50">
      <slot name="advanced-filters" />
    </div>

    <!-- Loading indicator -->
    <div v-if="loading" class="h-0.5 bg-gray-200 overflow-hidden">
      <div class="h-full bg-brand-primary animate-pulse w-1/3" />
    </div>
  </div>
</template>

<style scoped>
.action-bar {
  scrollbar-width: none;
}
.action-bar::-webkit-scrollbar {
  display: none;
}
:deep(.action-btn) {
  @apply inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-800 transition-colors whitespace-nowrap;
}
:deep(.action-btn-primary) {
  @apply inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-brand-primary border border-brand-primary rounded-md hover:bg-brand-primary/90 transition-colors whitespace-nowrap;
}
</style>
