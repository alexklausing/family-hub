<script setup>
import { computed } from 'vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/composables/useConfirm'

const { confirmState, confirmAccepted, confirmDismissed } = useConfirm()

const isOpen = computed({
    get: () => !!confirmState.value,
    set: (v) => {
        if (!v) confirmDismissed()
    },
})
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-sm" :show-close-button="false">
            <DialogHeader>
                <DialogTitle class="text-xl">
                    {{ confirmState?.title }}
                </DialogTitle>
            </DialogHeader>

            <p
                v-if="confirmState?.message"
                class="text-foreground/80 text-base leading-relaxed"
            >
                {{ confirmState.message }}
            </p>

            <DialogFooter class="mt-2 gap-2">
                <Button
                    variant="outline"
                    size="lg"
                    @click="confirmDismissed"
                >
                    {{ confirmState?.cancelLabel }}
                </Button>
                <Button
                    :variant="confirmState?.destructive ? 'destructive' : 'default'"
                    size="lg"
                    @click="confirmAccepted"
                >
                    {{ confirmState?.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
