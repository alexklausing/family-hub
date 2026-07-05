<script setup>
import { computed } from 'vue'
import { Plus, X, Pencil, ArrowLeftRight, ArrowUpDown, LayoutTemplate, Check, Trash2, Minus } from 'lucide-vue-next'
import AppRenderer from './AppRenderer.vue'

const props = defineProps({
    workspace: {
        type: Object,
        required: true
    },
    isEditing: {
        type: Boolean,
        default: false
    },
    // Dashboard Global Props (passed down to AppRenderer)
    events: Array,
    scheduleEvents: Array,
    activeProfile: String,
    profiles: Array,
    availableCalendars: Array,
    defaultCalendarId: [Number, String, null],
    visibleCalendarIds: Array,
    localTimezone: String,
})

const emit = defineEmits([
    'update:activeProfile',
    'update:defaultCalendar',
    'toggle-calendar',
    'reorder-calendars',
    'range-changed',
    'add-app',
    'remove-app',
    'swap-apps',
    'rename-workspace',
    'cycle-layout',
    'add-slot',
    'remove-slot',
    'close-edit'
])

// A workspace object looks like:
// { id: 'ws1', name: 'Weather', layout: 'split-vertical', apps: ['weather', 'recipes'] }

const layoutClass = computed(() => {
    const l = props.workspace.layout || 'full'
    if (l === 'full') return 'grid grid-cols-1 grid-rows-1'
    if (l === 'split-horizontal') return 'grid grid-cols-1 grid-rows-2 gap-6'
    if (l === 'split-vertical') return 'grid grid-cols-2 grid-rows-1 gap-6'
    if (l === 'sidebar-right') return 'grid grid-cols-2 grid-rows-2 gap-6'
    if (l === 'sidebar-left') return 'grid grid-cols-2 grid-rows-2 gap-6'
    if (l === 'grid-2x2') return 'grid grid-cols-2 grid-rows-2 gap-6'
    return 'grid grid-cols-1 grid-rows-1'
})

const getSlotClass = (index, total) => {
    const l = props.workspace.layout || 'full'
    if (total === 3) {
        if (l === 'sidebar-right' && index === 0) return 'row-span-2'
        if (l === 'sidebar-left' && index === 2) return 'row-span-2 col-start-2 row-start-1'
    }
    return ''
}
</script>

<template>
    <div class="relative w-full h-full p-2">
        <div :class="['w-full h-full', layoutClass]">
            <template v-if="workspace.apps.length > 0">
                <div 
                    v-for="(appId, index) in workspace.apps" 
                    :key="`${workspace.id}-${index}`"
                    :class="['relative min-h-0 min-w-0 bg-slate-200 dark:bg-slate-800 rounded-3xl overflow-hidden', getSlotClass(index, workspace.apps.length)]"
                >
                    <AppRenderer
                        v-if="appId"
                        :app-id="appId"
                        :events="events"
                        :scheduleEvents="scheduleEvents"
                        :activeProfile="activeProfile"
                        :profiles="profiles"
                        :availableCalendars="availableCalendars"
                        :defaultCalendarId="defaultCalendarId"
                        :visibleCalendarIds="visibleCalendarIds"
                        :localTimezone="localTimezone"
                        @update:activeProfile="emit('update:activeProfile', $event)"
                        @update:defaultCalendar="emit('update:defaultCalendar', $event)"
                        @toggle-calendar="emit('toggle-calendar', $event)"
                        @reorder-calendars="emit('reorder-calendars', $event)"
                        @range-changed="emit('range-changed', $event)"
                    />
                    
                    <!-- Empty Slot Placeholder -->
                    <div v-else class="w-full h-full relative flex flex-col items-center justify-center bg-white/5 dark:bg-black/10">
                        <button 
                            v-if="isEditing"
                            @click="emit('add-app', index)"
                            class="w-full h-full flex flex-col items-center justify-center hover:bg-white/10 dark:hover:bg-white/5 transition-colors group cursor-pointer"
                        >
                            <div class="w-16 h-16 bg-indigo-500 rounded-full flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-active:scale-95 transition-transform mb-4">
                                <Plus class="w-8 h-8" />
                            </div>
                            <span class="text-lg font-bold text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-300">Add App to Slot</span>
                        </button>
                        
                        <!-- Remove Slot Button -->
                        <button 
                            v-if="isEditing && workspace.apps.length > 1"
                            @click.stop="emit('remove-slot', index)"
                            class="absolute top-4 right-4 w-12 h-12 bg-red-500 rounded-full flex items-center justify-center text-white shadow-xl hover:scale-110 active:scale-95 transition-transform z-50"
                            title="Remove Empty Slot"
                        >
                            <Trash2 class="w-6 h-6" />
                        </button>
                    </div>
                    
                    <!-- Edit Mode Overlay for this Slot -->
                    <div v-if="isEditing && appId" class="absolute inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center gap-4 animate-in fade-in duration-200">
                        <button 
                            @click="emit('remove-app', index)"
                            class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center text-white shadow-xl hover:scale-110 active:scale-95 transition-transform"
                            title="Remove App"
                        >
                            <Minus class="w-8 h-8" stroke-width="3" />
                        </button>
                        <button 
                            v-if="workspace.apps.length > 1"
                            @click="emit('swap-apps', index)"
                            class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center text-white shadow-xl hover:scale-110 active:scale-95 transition-transform"
                        >
                            <ArrowLeftRight class="w-8 h-8" />
                        </button>
                        <button 
                            v-if="workspace.apps.length > 1"
                            @click="emit('swap-apps', index)"
                            class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center text-white shadow-xl hover:scale-110 active:scale-95 transition-transform"
                        >
                            <ArrowUpDown class="w-8 h-8" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Global Edit Actions for this Workspace -->
        <div v-if="isEditing" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-[60] flex items-center gap-2 bg-slate-900 dark:bg-white text-white dark:text-black px-4 py-3 rounded-full shadow-2xl animate-in slide-in-from-bottom-4 duration-300">
            <button 
                @click.stop="emit('close-edit')"
                class="flex items-center gap-2 px-4 py-2 bg-green-500 rounded-full hover:bg-green-600 text-white transition-colors"
                title="Done Editing"
            >
                <Check class="w-5 h-5" />
                <span class="font-bold">Done</span>
            </button>
            <div class="w-px h-6 bg-white/20 dark:bg-black/20 mx-1"></div>
            <button 
                v-if="workspace.apps.length < 4"
                @click.stop="emit('add-slot')"
                class="flex items-center gap-2 px-4 py-2 bg-indigo-500 rounded-full hover:bg-indigo-600 text-white transition-colors whitespace-nowrap"
                title="Add New App Slot"
            >
                <Plus class="w-5 h-5" />
                <span class="font-bold">Add Slot</span>
            </button>
            <div v-if="workspace.apps.length < 4" class="w-px h-6 bg-white/20 dark:bg-black/20 mx-1"></div>
            <button 
                @click.stop="emit('cycle-layout')"
                class="p-3 bg-white/20 dark:bg-black/10 rounded-full hover:bg-white/30 dark:hover:bg-black/20 transition-colors"
                title="Change Layout Template"
            >
                <LayoutTemplate class="w-5 h-5" />
            </button>
            <div class="w-px h-6 bg-white/20 dark:bg-black/20 mx-1"></div>
            <span class="font-black text-lg tracking-wide px-2 whitespace-nowrap">{{ workspace.name }}</span>
            <div class="w-px h-6 bg-white/20 dark:bg-black/20 mx-1"></div>
            <button 
                @click.stop="emit('rename-workspace')"
                class="p-3 bg-white/20 dark:bg-black/10 rounded-full hover:bg-white/30 dark:hover:bg-black/20 transition-colors"
                title="Rename Workspace"
            >
                <Pencil class="w-5 h-5" />
            </button>
        </div>
    </div>
</template>
