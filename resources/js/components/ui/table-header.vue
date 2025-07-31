<script setup lang="ts">
import { cn } from '@/lib/utils'

export interface TableHeaderProps {
  class?: string
}

const props = defineProps<TableHeaderProps>()
</script>

<template>
  <thead :class="cn('[&_tr]:border-b', props.class)">
    <slot />
  </thead>
</template>