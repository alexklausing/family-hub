<script setup>
import { XIcon } from '@lucide/vue'

import { reactiveOmit } from '@vueuse/core'
import {
    DialogClose,
    DialogContent,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import DialogOverlay from './DialogOverlay.vue'

defineOptions({
    inheritAttrs: false,
})

const props = defineProps({
    forceMount: { type: Boolean, required: false },
    disableOutsidePointerEvents: { type: Boolean, required: false },
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: {
        type: [Boolean, null, String, Object, Array],
        required: false,
        skipCheck: true,
    },
    showCloseButton: { type: Boolean, required: false, default: true },
})
const emits = defineEmits([
    'escapeKeyDown',
    'pointerDownOutside',
    'focusOutside',
    'interactOutside',
    'openAutoFocus',
    'closeAutoFocus',
])

const delegatedProps = reactiveOmit(props, 'class')

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
    <DialogPortal>
        <DialogOverlay />
        <DialogContent
            data-slot="dialog-content"
            v-bind="{ ...$attrs, ...forwarded }"
            @interact-outside="(e) => {
                if (e.target.closest('.virtual-keyboard-wrapper')) {
                    e.preventDefault()
                }
            }"
            :class="
                cn(
                    'bg-popover text-popover-foreground data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 ring-foreground/10 fixed top-1/2 left-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl p-4 text-sm ring-1 duration-100 outline-none sm:max-w-sm',
                    props.class,
                )
            "
        >
            <slot />

            <DialogClose
                v-if="showCloseButton"
                data-slot="dialog-close"
                as-child
            >
                <Button
                    variant="ghost"
                    class="absolute top-6 right-6 h-14 w-14 rounded-full bg-slate-100/50 hover:bg-slate-200/80 dark:bg-slate-800/50 dark:hover:bg-slate-700/80 backdrop-blur-sm transition-all"
                    size="icon"
                >
                    <XIcon class="h-6 w-6 opacity-70" />
                    <span class="sr-only">Close</span>
                </Button>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
