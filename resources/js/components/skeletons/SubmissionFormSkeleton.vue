<script setup>
import SkeletonBox from './SkeletonBox.vue'
</script>

<template>
  <div class="space-y-4">
    <SkeletonBox height="2.5rem" class="w-48" />
    <SkeletonBox height="2.5rem" class="w-full" />
    <SkeletonBox height="2.5rem" class="w-full" />
    <SkeletonBox height="8rem" class="w-full" />
  </div>
</template>
