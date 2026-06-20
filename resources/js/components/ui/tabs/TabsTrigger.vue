<script setup>
import { computed } from 'vue'
import { TabsTrigger, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps({
    value: { type: [String, Number], required: true },
    disabled: { type: Boolean, required: false },
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
    <TabsTrigger
        v-bind="forwardedProps"
        :class="
            cn(
                'data-[state=active]:bg-background data-[state=active]:text-foreground hover:bg-background/50 inline-flex items-center justify-center rounded-xl px-6 py-2 text-sm font-bold tracking-tight whitespace-nowrap transition-all focus-visible:outline-none active:scale-95 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:scale-[1.02] data-[state=active]:shadow-lg',
                props.class,
            )
        "
    >
        <slot />
    </TabsTrigger>
</template>
