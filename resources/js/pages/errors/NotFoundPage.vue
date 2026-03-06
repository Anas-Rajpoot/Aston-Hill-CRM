<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="flex min-h-[calc(100vh-4rem)] items-center justify-center bg-gray-50 px-4">
    <div class="text-center">
      <p class="text-6xl font-bold text-gray-300">404</p>
      <h1 class="mt-4 text-2xl font-semibold text-gray-800">Page Not Found</h1>
      <p class="mt-2 text-gray-500">The page you are looking for does not exist or has been moved.</p>
      <div class="mt-8 flex items-center justify-center gap-3">
        <button
          type="button"
          class="rounded-lg bg-brand-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2"
          @click="router.push('/')"
        >
          Go to Dashboard
        </button>
        <button
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2"
          @click="router.back()"
        >
          Go Back
        </button>
      </div>
    </div>
  </div>
</template>
