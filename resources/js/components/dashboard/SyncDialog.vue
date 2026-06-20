<script setup>
import { computed } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { RefreshCw } from 'lucide-vue-next'

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
})

const emit = defineEmits(['update:open', 'sync'])

const isOpen = computed({
    get: () => props.open,
    set: (val) => emit('update:open', val),
})

const selectSyncOption = (option) => {
    emit('sync', option)
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="rounded-[3rem] border-none bg-white/95 p-10 shadow-none backdrop-blur-3xl sm:max-w-[500px] dark:bg-black/95"
        >
            <DialogHeader class="mb-10 text-center">
                <div
                    class="bg-primary/10 mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl"
                >
                    <RefreshCw class="text-primary h-10 w-10" />
                </div>
                <DialogTitle
                    class="w-full text-center text-4xl font-black tracking-tight uppercase italic"
                    >Sync Data</DialogTitle
                >
                <DialogDescription
                    class="w-full text-center text-xl font-bold opacity-60"
                >
                    Choose refresh type.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <Button
                    variant="outline"
                    class="hover:border-primary/50 hover:bg-primary/5 flex h-24 w-full flex-col items-center justify-center gap-1 rounded-3xl border-2 text-left transition-all"
                    @click="selectSyncOption('current')"
                >
                    <span class="text-xl font-black tracking-tight"
                        >Active Tab Only</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-widest uppercase italic opacity-40"
                        >Fastest Refresh</span
                    >
                </Button>
                <Button
                    variant="outline"
                    class="hover:border-primary/50 hover:bg-primary/5 flex h-24 w-full flex-col items-center justify-center gap-1 rounded-3xl border-2 text-left transition-all"
                    @click="selectSyncOption('all')"
                >
                    <span class="text-xl font-black tracking-tight"
                        >Full System Refresh</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-widest uppercase italic opacity-40"
                        >Syncs All Calendars & Recipes</span
                    >
                </Button>
            </div>

            <div class="mt-10 flex w-full justify-center">
                <Button
                    variant="ghost"
                    class="text-lg font-black tracking-widest uppercase opacity-40 hover:opacity-100"
                    @click="isOpen = false"
                    >Cancel</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
