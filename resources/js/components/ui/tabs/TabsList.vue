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
        :class="
            cn(
                'bg-muted/30 text-muted-foreground inline-flex h-14 items-center justify-center rounded-2xl border border-white/10 p-1 shadow-inner backdrop-blur-xl',
                props.class,
            )
        "
    >
        <slot />
    </TabsList>
</template>
