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
    localTimezone: {
        type: String,
        required: true,
    },
})

const emit = defineEmits([
    'update:open',
    'update:isEditMode',
    'update:localTimezone',
    'open-sync',
])

const isOpen = computed({
    get: () => props.open,
    set: (val) => emit('update:open', val),
})

const editMode = computed({
    get: () => props.isEditMode,
    set: (val) => emit('update:isEditMode', val),
})

const timezone = computed({
    get: () => props.localTimezone,
    set: (val) => emit('update:localTimezone', val),
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

                <!-- Timezone Control -->
                <div
                    class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8"
                >
                    <div class="mb-4 flex items-center gap-5">
                        <div
                            class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-8 w-8"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 2v20" />
                                <path d="M2 12h20" />
                                <path
                                    d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xl font-black tracking-tight">
                                Timezone
                            </h4>
                            <p
                                class="text-[10px] font-bold tracking-widest uppercase opacity-40"
                            >
                                Local Date/Time display
                            </p>
                        </div>
                    </div>
                    <select
                        v-model="timezone"
                        class="bg-primary/10 text-primary focus:ring-primary/50 h-14 w-full rounded-2xl border-none px-4 text-sm font-bold outline-none focus:ring-2"
                    >
                        <option value="America/New_York">
                            Eastern Time (America/New_York)
                        </option>
                        <option value="America/Chicago">
                            Central Time (America/Chicago)
                        </option>
                        <option value="America/Denver">
                            Mountain Time (America/Denver)
                        </option>
                        <option value="America/Phoenix">
                            Mountain Time - No DST (America/Phoenix)
                        </option>
                        <option value="America/Los_Angeles">
                            Pacific Time (America/Los_Angeles)
                        </option>
                        <option value="America/Anchorage">
                            Alaska Time (America/Anchorage)
                        </option>
                        <option value="Pacific/Honolulu">
                            Hawaii Time (Pacific/Honolulu)
                        </option>
                        <option value="UTC">UTC</option>
                    </select>
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
