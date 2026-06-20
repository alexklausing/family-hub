<script setup>
import { computed } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Lock, Unlock, RefreshCw, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    isEditMode: {
        type: Boolean,
        required: true,
    },
    isSyncing: {
        type: Boolean,
        required: true,
    },
})

const emit = defineEmits(['update:open', 'update:isEditMode', 'open-sync'])

const isOpen = computed({
    get: () => props.open,
    set: (val) => emit('update:open', val),
})

const editMode = computed({
    get: () => props.isEditMode,
    set: (val) => emit('update:isEditMode', val),
})
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="rounded-[3rem] border-none bg-white/95 p-10 shadow-none backdrop-blur-3xl sm:max-w-[425px] dark:bg-black/95"
        >
            <DialogHeader class="mb-8">
                <DialogTitle class="text-4xl font-black tracking-tighter italic"
                    >Settings</DialogTitle
                >
                <DialogDescription
                    class="text-lg font-bold tracking-widest uppercase italic opacity-60"
                >
                    System Controls
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6">
                <!-- Lock Control -->
                <div
                    class="bg-muted/20 flex items-center justify-between rounded-[2rem] border border-white/5 p-8"
                >
                    <div class="flex items-center gap-5">
                        <div
                            :class="[
                                'flex h-14 w-14 items-center justify-center rounded-2xl',
                                editMode
                                    ? 'bg-orange-500/20 text-orange-500'
                                    : 'bg-primary/20 text-primary',
                            ]"
                        >
                            <component
                                :is="editMode ? Unlock : Lock"
                                class="h-8 w-8"
                            />
                        </div>
                        <div>
                            <h4 class="text-xl font-black tracking-tight">
                                {{ editMode ? 'Edit Mode' : 'Locked' }}
                            </h4>
                            <p
                                class="text-[10px] font-bold tracking-widest uppercase opacity-40"
                            >
                                Layout Safety
                            </p>
                        </div>
                    </div>
                    <Switch
                        :checked="editMode"
                        @update:checked="editMode = $event"
                        class="scale-150"
                    />
                </div>

                <!-- Sync Control -->
                <div
                    @click="emit('open-sync')"
                    class="bg-muted/20 hover:bg-muted/40 group flex w-full cursor-pointer items-center justify-between rounded-[2rem] border border-white/5 p-8 text-left transition-all"
                >
                    <div class="flex items-center gap-5">
                        <div
                            class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl"
                        >
                            <RefreshCw
                                :class="[
                                    'h-8 w-8',
                                    isSyncing ? 'animate-spin' : '',
                                ]"
                            />
                        </div>
                        <div>
                            <h4
                                class="group-hover:text-primary text-xl font-black tracking-tight transition-colors"
                            >
                                Data Refresh
                            </h4>
                            <p
                                class="text-[10px] font-bold tracking-widest uppercase opacity-40"
                            >
                                External Sources
                            </p>
                        </div>
                    </div>
                    <ChevronRight
                        class="h-8 w-8 opacity-20 transition-all group-hover:opacity-100"
                    />
                </div>
            </div>

            <div class="mt-10">
                <Button
                    class="h-16 w-full rounded-2xl text-xl font-black shadow-none transition-transform hover:scale-[1.02] active:scale-95"
                    @click="isOpen = false"
                    >Close</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
