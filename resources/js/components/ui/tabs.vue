<script setup lang="ts">
import { provide, ref, computed } from 'vue'
import { cn } from '@/lib/utils'

export interface TabsProps {
  defaultValue?: string
  orientation?: 'horizontal' | 'vertical'
  class?: string
}

const props = withDefaults(defineProps<TabsProps>(), {
  orientation: 'horizontal',
})

const activeTab = ref(props.defaultValue || '')

provide('tabs', {
  activeTab,
  orientation: props.orientation,
})
</script>

<template>
  <div :class="cn('', props.class)">
    <slot />
  </div>
</template>