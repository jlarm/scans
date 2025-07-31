<script setup lang="ts">
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

export interface TabsTriggerProps {
  value: string
  disabled?: boolean
  class?: string
}

const props = defineProps<TabsTriggerProps>()

const tabs = inject('tabs') as any
const isActive = computed(() => tabs.activeTab.value === props.value)

const handleClick = () => {
  if (!props.disabled) {
    tabs.activeTab.value = props.value
  }
}
</script>

<template>
  <button
    :class="cn(
      'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
      isActive 
        ? 'bg-background text-foreground shadow-sm' 
        : 'hover:bg-muted/50',
      props.class
    )"
    :disabled="disabled"
    role="tab"
    :aria-selected="isActive"
    @click="handleClick"
  >
    <slot />
  </button>
</template>