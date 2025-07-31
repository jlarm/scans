<script setup lang="ts">
import { provide, ref } from 'vue'

export interface CollapsibleProps {
  defaultOpen?: boolean
}

const props = withDefaults(defineProps<CollapsibleProps>(), {
  defaultOpen: false,
})

const isOpen = ref(props.defaultOpen)

provide('collapsible', {
  isOpen,
  toggle: () => { isOpen.value = !isOpen.value },
})
</script>

<template>
  <div>
    <slot />
  </div>
</template>