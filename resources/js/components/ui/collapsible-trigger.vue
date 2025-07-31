<script setup lang="ts">
import { inject } from 'vue'

export interface CollapsibleTriggerProps {
  asChild?: boolean
}

const props = defineProps<CollapsibleTriggerProps>()
const collapsible = inject('collapsible') as any

const handleClick = (event: Event) => {
  event.preventDefault()
  collapsible.toggle()
}
</script>

<template>
  <button
    v-if="!asChild"
    @click="handleClick"
    type="button"
  >
    <slot />
  </button>
  <div
    v-else
    @click="handleClick"
    style="cursor: pointer;"
  >
    <slot />
  </div>
</template>