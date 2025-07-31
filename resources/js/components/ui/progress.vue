<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

export interface ProgressProps {
  value?: number
  max?: number
  class?: string
}

const props = withDefaults(defineProps<ProgressProps>(), {
  value: 0,
  max: 100,
})

const percentage = computed(() => {
  if (!props.value || props.value < 0) return 0
  if (props.value > props.max) return 100
  return (props.value / props.max) * 100
})
</script>

<template>
  <div
    :class="cn('relative h-4 w-full overflow-hidden rounded-full bg-secondary', props.class)"
    role="progressbar"
    :aria-valuenow="value"
    :aria-valuemax="max"
  >
    <div
      class="h-full bg-primary transition-all duration-300 ease-in-out"
      :style="{ transform: `translateX(-${100 - percentage}%)` }"
    />
  </div>
</template>