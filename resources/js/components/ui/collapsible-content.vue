<script setup lang="ts">
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

export interface CollapsibleContentProps {
  class?: string
}

const props = defineProps<CollapsibleContentProps>()
const collapsible = inject('collapsible') as any
const isOpen = computed(() => collapsible.isOpen.value)
</script>

<template>
  <div
    v-show="isOpen"
    :class="cn('overflow-hidden', props.class)"
  >
    <slot />
  </div>
</template>