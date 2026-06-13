<script setup>
import { computed } from 'vue'
import { TabsList, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps({
  loop: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
})

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <TabsList
    v-bind="forwardedProps"
    :class="cn(
      'inline-flex h-14 items-center justify-center rounded-2xl bg-muted/30 p-1 text-muted-foreground backdrop-blur-xl border border-white/10 shadow-inner',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>
