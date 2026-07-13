<script setup>
import { computed, ref } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Lock, Unlock, RefreshCw, ChevronRight, Moon, Sun, Monitor, Globe, Map, Code, LayoutGrid } from 'lucide-vue-next'

const props = defineProps({
    open: {
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
    themePreference: {
        type: String,
        required: true,
    },
    defaultRadarLayers: {
        type: Object,
        required: true,
    },
    developerSettings: {
        type: Object,
        required: true,
    },
    isEditingLayouts: {
        type: Boolean,
        required: true,
    },
    continuousRecipeScroll: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'update:open',

    'update:localTimezone',
    'update:themePreference',
    'update:defaultRadarLayers',
    'update:developerSettings',
    'update:isEditingLayouts',
    'update:continuousRecipeScroll',
    'open-sync',
    'reset-layouts',
])

const isOpen = computed({
    get: () => props.open,
    set: (val) => emit('update:open', val),
})


const timezone = computed({
    get: () => props.localTimezone,
    set: (val) => emit('update:localTimezone', val),
})

const theme = computed({
    get: () => props.themePreference,
    set: (val) => emit('update:themePreference', val),
})

const defaultLayers = computed({
    get: () => props.defaultRadarLayers,
    set: (val) => emit('update:defaultRadarLayers', val),
})

const devSettings = computed({
    get: () => props.developerSettings,
    set: (val) => emit('update:developerSettings', val),
})

const continuousScroll = computed({
    get: () => props.continuousRecipeScroll,
    set: (val) => emit('update:continuousRecipeScroll', val),
})

const startEditingLayout = () => {
    emit('update:isEditingLayouts', true)
    isOpen.value = false
}

const resetLayouts = () => {
    if (confirm('Are you sure you want to reset all tabs and layouts to default? This cannot be undone.')) {
        emit('reset-layouts')
        isOpen.value = false
    }
}

const activeTab = ref('general')

import axios from 'axios'

const toggleAllDeveloperFeatures = () => {
    // If testWeatherAlerts is currently true, set all to false. Otherwise set all to true.
    const newState = !devSettings.value.testWeatherAlerts
    devSettings.value = {
        ...devSettings.value,
        testWeatherAlerts: newState
    }
}

const refreshKiosk = async () => {
    try {
        await axios.post('/api/kiosk/refresh')
        // Give some visual feedback if desired, or just close
        isOpen.value = false
    } catch (e) {
        console.error('Failed to trigger refresh:', e)
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="rounded-[3rem] border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[850px] md:max-w-[950px] dark:bg-black/95"
        >
            <div class="flex flex-col md:flex-row gap-8 h-[600px] max-h-[80vh]">
                <!-- Sidebar -->
                <div class="w-full md:w-64 flex flex-col gap-2 shrink-0 md:border-r border-white/10 md:pr-6">
                    <DialogHeader class="mb-6 text-left">
                        <DialogTitle class="text-4xl font-black tracking-tighter italic">Settings</DialogTitle>
                        <DialogDescription class="text-lg font-bold tracking-widest uppercase italic opacity-60">
                            System Controls
                        </DialogDescription>
                    </DialogHeader>

                    <nav class="flex flex-col gap-2 flex-1">
                        <button 
                            @click="activeTab = 'general'"
                            :class="['flex items-center gap-4 px-4 py-4 rounded-2xl font-bold transition-all text-left', activeTab === 'general' ? 'bg-primary/20 text-primary' : 'hover:bg-white/5 opacity-60 hover:opacity-100']"
                        >
                            <Globe class="h-6 w-6 shrink-0" /> <span class="text-lg">General</span>
                        </button>
                        <button 
                            @click="activeTab = 'appearance'"
                            :class="['flex items-center gap-4 px-4 py-4 rounded-2xl font-bold transition-all text-left', activeTab === 'appearance' ? 'bg-primary/20 text-primary' : 'hover:bg-white/5 opacity-60 hover:opacity-100']"
                        >
                            <Monitor class="h-6 w-6 shrink-0" /> <span class="text-lg">Appearance</span>
                        </button>
                        <button 
                            @click="activeTab = 'weather'"
                            :class="['flex items-center gap-4 px-4 py-4 rounded-2xl font-bold transition-all text-left', activeTab === 'weather' ? 'bg-primary/20 text-primary' : 'hover:bg-white/5 opacity-60 hover:opacity-100']"
                        >
                            <Map class="h-6 w-6 shrink-0" /> <span class="text-lg">Weather Maps</span>
                        </button>
                        <button 
                            @click="activeTab = 'data'"
                            :class="['flex items-center gap-4 px-4 py-4 rounded-2xl font-bold transition-all text-left', activeTab === 'data' ? 'bg-primary/20 text-primary' : 'hover:bg-white/5 opacity-60 hover:opacity-100']"
                        >
                            <RefreshCw class="h-6 w-6 shrink-0" /> <span class="text-lg">Data Sync</span>
                        </button>
                        <button 
                            @click="activeTab = 'developer'"
                            :class="['flex items-center gap-4 px-4 py-4 rounded-2xl font-bold transition-all text-left', activeTab === 'developer' ? 'bg-primary/20 text-primary' : 'hover:bg-white/5 opacity-60 hover:opacity-100']"
                        >
                            <Code class="h-6 w-6 shrink-0" /> <span class="text-lg">Developer</span>
                        </button>
                    </nav>

                    <div class="mt-auto shrink-0 pt-4 hidden md:block">
                        <Button
                            class="h-16 w-full rounded-2xl text-xl font-black shadow-none transition-transform hover:scale-[1.02] active:scale-95"
                            @click="isOpen = false"
                        >Save & Close</Button>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto custom-scrollbar md:pr-4 py-2">
                    
                    <!-- General Tab -->
                    <div v-show="activeTab === 'general'" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                        <div class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8">
                            <div class="mb-4 flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <Globe class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="text-xl font-black tracking-tight">Timezone</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Local Date/Time display</p>
                                </div>
                            </div>
                            <select
                                v-model="timezone"
                                class="bg-primary/10 text-primary focus:ring-primary/50 h-14 w-full rounded-2xl border-none px-4 text-sm font-bold outline-none focus:ring-2"
                            >
                                <option value="America/New_York">Eastern Time (America/New_York)</option>
                                <option value="America/Chicago">Central Time (America/Chicago)</option>
                                <option value="America/Denver">Mountain Time (America/Denver)</option>
                                <option value="America/Phoenix">Mountain Time - No DST (America/Phoenix)</option>
                                <option value="America/Los_Angeles">Pacific Time (America/Los_Angeles)</option>
                                <option value="America/Anchorage">Alaska Time (America/Anchorage)</option>
                                <option value="Pacific/Honolulu">Hawaii Time (Pacific/Honolulu)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>

                        <div class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8">
                            <div class="mb-6 flex items-center justify-between gap-5">
                                <div class="flex items-center gap-5">
                                    <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                        <LayoutGrid class="h-8 w-8" />
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-black tracking-tight">Recipes Continuous View</h4>
                                        <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Load more automatically when scrolling</p>
                                    </div>
                                </div>
                                <Switch :checked="continuousScroll" @update:checked="val => continuousScroll = val" />
                            </div>
                        </div>
                    </div>

                    <!-- Appearance Tab -->
                    <div v-show="activeTab === 'appearance'" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                        <div class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8">
                            <div class="mb-6 flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <Monitor class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="text-xl font-black tracking-tight">Appearance</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Interface Theme</p>
                                </div>
                            </div>
                            <div class="flex gap-2 bg-black/5 dark:bg-white/5 p-1.5 rounded-[1.5rem]">
                                <button
                                    @click="theme = 'auto'"
                                    :class="['flex-1 flex items-center justify-center gap-2 h-14 rounded-xl font-bold transition-all', theme === 'auto' ? 'bg-white text-black dark:bg-[#222] dark:text-white shadow-sm' : 'opacity-60 hover:opacity-100']"
                                >
                                    <Monitor class="w-4 h-4" /> Auto
                                </button>
                                <button
                                    @click="theme = 'light'"
                                    :class="['flex-1 flex items-center justify-center gap-2 h-14 rounded-xl font-bold transition-all', theme === 'light' ? 'bg-white text-black dark:bg-[#222] dark:text-white shadow-sm' : 'opacity-60 hover:opacity-100']"
                                >
                                    <Sun class="w-4 h-4" /> Light
                                </button>
                                <button
                                    @click="theme = 'dark'"
                                    :class="['flex-1 flex items-center justify-center gap-2 h-14 rounded-xl font-bold transition-all', theme === 'dark' ? 'bg-white text-black dark:bg-[#222] dark:text-white shadow-sm' : 'opacity-60 hover:opacity-100']"
                                >
                                    <Moon class="w-4 h-4" /> Dark
                                </button>
                            </div>
                        </div>
                        <div class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8">
                            <div class="mb-6 flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <LayoutGrid class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="text-xl font-black tracking-tight">Dashboard Layout</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Rearrange tabs & widgets</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <Button
                                    @click="resetLayouts"
                                    variant="secondary"
                                    class="h-14 flex-1 rounded-2xl font-black transition-all shadow-none"
                                >
                                    Reset to Default
                                </Button>
                                <Button 
                                    @click="startEditingLayout"
                                    variant="secondary"
                                    class="h-14 flex-1 rounded-2xl font-black transition-all shadow-none"
                                >
                                    Edit Layout
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Weather Tab -->
                    <div v-show="activeTab === 'weather'" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                        <div class="bg-muted/20 flex flex-col justify-between rounded-[2rem] border border-white/5 p-8">
                            <div class="mb-6 flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <Map class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="text-xl font-black tracking-tight">Default Radar Layers</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Initial Map Settings</p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold opacity-80 text-lg">Storm & Rain</span>
                                    <Switch :checked="defaultLayers.precipitation" @update:checked="val => defaultLayers = { ...defaultLayers, precipitation: val }" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold opacity-80 text-lg">Severe Weather</span>
                                    <Switch :checked="defaultLayers.warnings" @update:checked="val => defaultLayers = { ...defaultLayers, warnings: val }" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold opacity-80 text-lg">Wildfires</span>
                                    <Switch :checked="defaultLayers.wildfires" @update:checked="val => defaultLayers = { ...defaultLayers, wildfires: val }" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold opacity-80 text-lg">Hurricanes</span>
                                    <Switch :checked="defaultLayers.hurricanes" @update:checked="val => defaultLayers = { ...defaultLayers, hurricanes: val }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Tab -->
                    <div v-show="activeTab === 'data'" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                        <div
                            @click="emit('open-sync')"
                            class="bg-muted/20 hover:bg-muted/40 group flex w-full cursor-pointer items-center justify-between rounded-[2rem] border border-white/5 p-8 text-left transition-all"
                        >
                            <div class="flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <RefreshCw :class="['h-8 w-8', isSyncing ? 'animate-spin' : '']" />
                                </div>
                                <div>
                                    <h4 class="group-hover:text-primary text-xl font-black tracking-tight transition-colors">Data Refresh</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">External Sources</p>
                                </div>
                            </div>
                            <ChevronRight class="h-8 w-8 opacity-20 transition-all group-hover:opacity-100 shrink-0" />
                        </div>

                        <div
                            @click="refreshKiosk"
                            class="bg-muted/20 hover:bg-muted/40 group flex w-full cursor-pointer items-center justify-between rounded-[2rem] border border-white/5 p-8 text-left transition-all"
                        >
                            <div class="flex items-center gap-5">
                                <div class="bg-primary/20 text-primary flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                    <Monitor class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="group-hover:text-primary text-xl font-black tracking-tight transition-colors">Remote Screen Refresh</h4>
                                    <p class="text-[10px] font-bold tracking-widest uppercase opacity-40">Reload All Physical Kiosks</p>
                                </div>
                            </div>
                            <ChevronRight class="h-8 w-8 opacity-20 transition-all group-hover:opacity-100 shrink-0" />
                        </div>
                    </div>
                    
                    <!-- Developer Tab -->
                    <div v-show="activeTab === 'developer'" class="grid gap-6 animate-in fade-in slide-in-from-right-4 duration-300">
                        <div class="bg-red-500/10 flex flex-col justify-between rounded-[2rem] border border-red-500/20 p-8">
                            <div class="mb-6 flex items-center justify-between gap-5">
                                <div class="flex items-center gap-5">
                                    <div class="bg-red-500/20 text-red-500 flex h-14 w-14 items-center justify-center rounded-2xl shrink-0">
                                        <Code class="h-8 w-8" />
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-black tracking-tight text-red-600 dark:text-red-400">Developer Mode</h4>
                                        <p class="text-[10px] font-bold tracking-widest uppercase opacity-60 text-red-600 dark:text-red-400">Advanced Features</p>
                                    </div>
                                </div>
                                <Switch :checked="devSettings.masterToggle" @update:checked="val => devSettings = { ...devSettings, masterToggle: val }" />
                            </div>
                            
                            <div class="flex flex-col gap-4 border-t border-red-500/20 pt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-black text-sm uppercase tracking-widest text-red-600 dark:text-red-400">Test Features</span>
                                    <Button variant="ghost" size="sm" @click="toggleAllDeveloperFeatures" class="text-red-600 dark:text-red-400 hover:text-red-700 hover:bg-red-500/20">
                                        Toggle All
                                    </Button>
                                </div>
                                
                                <div class="flex items-center justify-between" :class="!devSettings.masterToggle ? 'opacity-50' : ''">
                                    <span class="font-bold text-lg">Test Weather Alerts</span>
                                    <Switch :checked="devSettings.testWeatherAlerts" @update:checked="val => devSettings = { ...devSettings, testWeatherAlerts: val }" />
                                </div>
                                <div class="flex items-center justify-between" :class="!devSettings.masterToggle ? 'opacity-50' : ''">
                                    <div class="space-y-0.5">
                                        <span class="font-bold text-lg">Hide Mouse Cursor</span>
                                        <p class="text-xs opacity-60">Hide cursor entirely on this specific device (for kiosks)</p>
                                    </div>
                                    <Switch :disabled="!devSettings.masterToggle" :checked="devSettings.hideCursor" @update:checked="val => devSettings = { ...devSettings, hideCursor: val }" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Mobile Close Button -->
                <div class="md:hidden mt-4 shrink-0">
                    <Button
                        class="h-16 w-full rounded-2xl text-xl font-black shadow-none transition-transform hover:scale-[1.02] active:scale-95"
                        @click="isOpen = false"
                    >Close</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
