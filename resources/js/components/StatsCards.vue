<script setup>
defineProps({
  total: { type: Number, default: 1247 },
  pending: { type: Number, default: 89 },
  cloud: { type: Number, default: 12 },
  breached: { type: Number, default: 0 },
  completed: { type: Number, default: 156 },
})
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
    <!-- Card 1: Blue - Document icon - Total -->
    <div class="bg-blue-500 text-white p-5 rounded-xl shadow flex items-center justify-between relative overflow-hidden">
      <div class="relative z-10">
        <p class="text-sm font-medium opacity-90">Total Submissions</p>
        <p class="text-2xl font-bold mt-1">{{ total.toLocaleString() }}</p>
      </div>
      <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center shrink-0 relative z-10">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
    </div>

    <!-- Card 2: Blue - Person icon - 89 -->
    <div class="bg-blue-500 text-white p-5 rounded-xl shadow flex items-center justify-between relative overflow-hidden">
      <div class="relative z-10">
        <p class="text-sm font-medium opacity-90">Pending Tasks</p>
        <p class="text-2xl font-bold mt-1">{{ pending.toLocaleString() }}</p>
      </div>
      <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center shrink-0 relative z-10">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
      </div>
    </div>

    <!-- Card 3: Light grey - Cloud icon - 12 (N badge) -->
    <div class="bg-gray-100 text-gray-800 p-5 rounded-xl shadow flex items-center justify-between relative">
      <div>
        <p class="text-sm font-medium text-gray-600">In Progress</p>
        <p class="text-2xl font-bold mt-1">{{ cloud.toLocaleString() }}</p>
      </div>
      <div class="w-12 h-12 rounded-lg bg-gray-200 flex items-center justify-center">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
        </svg>
      </div>
      <span class="absolute top-2 right-2 w-6 h-6 rounded-full bg-green-500 text-white text-xs font-bold flex items-center justify-center">N</span>
    </div>

    <!-- Card 4: Red - Alert icon (N badge) -->
    <div class="bg-red-500 text-white p-5 rounded-xl shadow flex items-center justify-between relative">
      <div>
        <p class="text-sm font-medium opacity-90">SLA Breached</p>
        <p class="text-2xl font-bold mt-1">{{ breached.toLocaleString() }}</p>
      </div>
      <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <span class="absolute top-2 right-2 w-6 h-6 rounded-full bg-green-500 text-white text-xs font-bold flex items-center justify-center">N</span>
    </div>

    <!-- Card 5: Green - Checkmark - 156 (N badge) -->
    <div class="bg-green-500 text-white p-5 rounded-xl shadow flex items-center justify-between relative">
      <div>
        <p class="text-sm font-medium opacity-90">Completed Today</p>
        <p class="text-2xl font-bold mt-1">{{ completed.toLocaleString() }}</p>
      </div>
      <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <span class="absolute top-2 right-2 w-6 h-6 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center">N</span>
    </div>
  </div>
</template>
