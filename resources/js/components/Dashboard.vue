<script setup>
import { onMounted, onUnmounted, ref, computed, watch, provide } from 'vue'
import { Tabs, TabsContent } from '@/components/ui/tabs'
import { Home, User, AlertTriangle, X, BellOff } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import WorkspaceView from './WorkspaceView.vue'
import OtherTab from './OtherTab.vue'
import AuraTab from './AuraTab.vue'

// Extracted Subcomponents & Composable
import { useDashboard } from './dashboard/useDashboard'
import DashboardHeader from './dashboard/DashboardHeader.vue'
import SettingsDialog from './dashboard/SettingsDialog.vue'
import SyncDialog from './dashboard/SyncDialog.vue'
import VirtualKeyboard from './VirtualKeyboard.vue'
import { useWeather } from '@/composables/useWeather'

const { alerts, fetchWeather, weatherData } = useWeather()

const {
    isSettingsDialogOpen,
    isSyncModalOpen,
    syncOption,
    activeProfile,
    availableCalendars,
    visibleCalendarIds,
    isSyncing,
    filteredEvents,
    filteredScheduleEvents,
    fetchEvents,
    toggleCalendar,
    handleSync,
    localTimezone,
    saveFilters,
    defaultCalendarId,
    reorderCalendars,
    workspaces,
    createWorkspace,
    removeWorkspace,
    updateWorkspace
} = useDashboard()

const activeTab = ref(workspaces.value?.[0]?.id || 'other')
const editingWorkspaceId = ref(null)
const unpinnedAppId = ref(null)
const addingToSlotIndex = ref(null)

const weatherView = ref('weather')
provide('weatherView', weatherView)

const themePreference = ref(localStorage.getItem('themePreference') || 'auto')

// Auto Dark Mode Logic
const updateTheme = () => {
    let isDark = false

    if (themePreference.value === 'dark') {
        isDark = true
    } else if (themePreference.value === 'light') {
        isDark = false
    } else {
        const now = Math.floor(Date.now() / 1000)
        if (weatherData.value?.current?.sunrise && weatherData.value?.current?.sunset) {
            const sunrise = weatherData.value.current.sunrise
            const sunset = weatherData.value.current.sunset
            isDark = now >= sunset || now < sunrise
        } else {
            // Fallback to strict hours (8pm to 6am) if API fails
            const hour = new Date().getHours()
            isDark = hour >= 20 || hour < 6
        }
    }
    
    if (isDark) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

watch(themePreference, (newVal) => {
    localStorage.setItem('themePreference', newVal)
    updateTheme()
})

const defaultRadarLayers = ref(JSON.parse(localStorage.getItem('defaultRadarLayers') || 'null') || {
    precipitation: true,
    warnings: false,
    wildfires: false,
    hurricanes: false
})

watch(defaultRadarLayers, (newVal) => {
    localStorage.setItem('defaultRadarLayers', JSON.stringify(newVal))
}, { deep: true })

const savedSettings = JSON.parse(localStorage.getItem('developerSettings') || 'null')
const developerSettings = ref({
    masterToggle: false,
    testWeatherAlerts: false,
    hideCursor: false,
    ...(savedSettings || {})
})
provide('developerSettings', developerSettings)

const applyCursorSetting = () => {
    if (developerSettings.value.hideCursor) {
        document.body.classList.add('cursor-none')
        // Hide cursor on all child elements too
        const style = document.createElement('style')
        style.id = 'hide-cursor-style'
        style.innerHTML = '* { cursor: none !important; }'
        if (!document.getElementById('hide-cursor-style')) {
            document.head.appendChild(style)
        }
    } else {
        document.body.classList.remove('cursor-none')
        const style = document.getElementById('hide-cursor-style')
        if (style) style.remove()
    }
}

watch(developerSettings, (newVal) => {
    localStorage.setItem('developerSettings', JSON.stringify(newVal))
    fetchWeather(true) // Refresh weather when developer settings change
    applyCursorSetting()
}, { deep: true })

onMounted(() => {
    applyCursorSetting()
})

watch(weatherData, updateTheme, { deep: true })
let themeInterval = null

import axios from 'axios'

// Global Dashboard Logic & State
const isBannerDismissed = ref(false)
const isMuteModalOpen = ref(false)
const mutedUntil = ref(parseInt(localStorage.getItem('weather_muted_until') || '0'))

const isBannerVisible = computed(() => {
    if (!alerts.value || alerts.value.length === 0) return false
    if (isBannerDismissed.value) return false
    if (Date.now() < mutedUntil.value) return false
    return true
})

const closeBanner = () => {
    isBannerDismissed.value = true
}

const muteBanner = (minutes) => {
    const muteTime = Date.now() + minutes * 60000
    mutedUntil.value = muteTime
    localStorage.setItem('weather_muted_until', muteTime.toString())
    isMuteModalOpen.value = false
}

// Kiosk Auto-Refresh Logic
const kioskVersion = ref(null)
let kioskInterval = null

onMounted(() => {
    fetchWeather()
    setInterval(() => fetchWeather(true), 600000)
    
    updateTheme()
    themeInterval = setInterval(updateTheme, 60000)

    // Kiosk Version Polling (Check every 15 seconds)
    axios.get('/api/kiosk/version').then(res => {
        kioskVersion.value = res.data.version
    }).catch(() => {})

    kioskInterval = setInterval(async () => {
        try {
            const res = await axios.get('/api/kiosk/version')
            if (kioskVersion.value !== null && res.data.version !== kioskVersion.value) {
                window.location.reload(true)
            }
        } catch (e) {
            // Silently ignore network errors to prevent console spam
        }
    }, 15000)
})

onUnmounted(() => {
    if (themeInterval) clearInterval(themeInterval)
    if (kioskInterval) clearInterval(kioskInterval)
})

// (Moved up for activeTab init)

// Profiles definition (static layout configuration)
const profiles = [
    { name: 'Family', icon: Home },
    { name: 'Alex', icon: User },
    { name: 'Sarah', icon: User },
    { name: 'Emily', icon: User },
    { name: 'Henry', icon: User },
]

const handleOpenSync = () => {
    isSettingsDialogOpen.value = false
    isSyncModalOpen.value = true
}

const handleSyncRequest = (option) => {
    syncOption.value = option
    handleSync()
}

const handleAppLaunch = (appId) => {
    // First see if we are editing a workspace to add to it
    if (editingWorkspaceId.value && addingToSlotIndex.value !== null) {
        const ws = workspaces.value.find(w => w.id === editingWorkspaceId.value)
        if (ws) {
            const w = { ...ws, apps: [...ws.apps] }
            w.apps[addingToSlotIndex.value] = appId
            
            updateWorkspace(ws.id, { apps: w.apps })
            
            activeTab.value = ws.id
            editingWorkspaceId.value = null
            addingToSlotIndex.value = null
            return
        }
    }
    
    // Otherwise just switch to the first workspace containing this app
    const existingWs = workspaces.value.find(w => w.apps.includes(appId))
    if (existingWs) {
        activeTab.value = existingWs.id
    } else {
        // Do not automatically pin. Just display it temporarily.
        unpinnedAppId.value = appId
        activeTab.value = 'unpinned'
    }
}
</script>

<template>
    <div
        class="flex h-screen max-h-screen flex-col overflow-hidden bg-[#f2f2f7] text-black dark:text-white p-4 font-sans transition-colors duration-500 lg:p-6 dark:bg-[#000000]"
        @click="() => { editingWorkspaceId = null; addingToSlotIndex = null; }"
    >
        <!-- Full Screen Aura Overlay -->
        <div v-if="activeTab === 'unpinned' && unpinnedAppId === 'aura'" class="fixed inset-0 z-[100] bg-black">
            <button @click="activeTab = 'other'" class="absolute top-6 left-6 z-[110] p-4 bg-white/10 hover:bg-white/30 text-white rounded-full backdrop-blur-md transition-colors">
                <X class="w-8 h-8" />
            </button>
            <AuraTab />
        </div>

        <Tabs v-model="activeTab" class="flex min-h-0 flex-1 flex-col gap-6">
            <!-- Header Island Component -->
            <DashboardHeader 
                :active-tab="activeTab" 
                :local-timezone="localTimezone" 
                :workspaces="workspaces" 
                :is-editing="editingWorkspaceId === activeTab"
                @open-settings="isSettingsDialogOpen = true" 
                @toggle-edit="editingWorkspaceId = editingWorkspaceId ? null : activeTab"
            />

            <!-- Global Weather Alerts Banner -->
            <div v-if="isBannerVisible" class="shrink-0 -mt-2">
                <div class="flex items-center gap-4 rounded-2xl bg-red-500/10 border-2 border-red-500/20 px-4 py-2 text-red-500 dark:text-red-400">
                    <div class="flex items-center gap-2 shrink-0 font-black tracking-widest uppercase text-sm animate-pulse ml-2">
                        <AlertTriangle class="h-5 w-5" />
                        Weather Alert
                    </div>
                    <div class="flex-1 overflow-hidden relative flex items-center h-6">
                        <div class="flex animate-marquee whitespace-nowrap absolute">
                            <span v-for="alert in alerts" :key="alert.id" class="mr-12 flex items-center gap-2 font-bold text-sm">
                                <span class="uppercase font-black text-red-600 dark:text-red-500">{{ alert.properties.event }}:</span>
                                <span class="opacity-80">{{ alert.properties.headline }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            @click="isMuteModalOpen = true"
                            class="p-2 rounded-xl hover:bg-red-500/20 active:bg-red-500/30 transition-colors"
                            title="Mute Alerts"
                        >
                            <BellOff class="h-5 w-5" />
                        </button>
                        <button
                            @click="closeBanner"
                            class="p-2 rounded-xl hover:bg-red-500/20 active:bg-red-500/30 transition-colors"
                            title="Close Banner"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="relative min-h-0 flex-1 overflow-hidden" @click.stop>
                <!-- Dynamic Content Workspaces -->
                <TabsContent
                    v-for="workspace in workspaces"
                    :key="workspace.id"
                    :value="workspace.id"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <WorkspaceView
                        :workspace="workspace"
                        :is-editing="editingWorkspaceId === workspace.id"
                        :events="filteredEvents"
                        :scheduleEvents="filteredScheduleEvents"
                        :activeProfile="activeProfile"
                        :profiles="profiles"
                        :availableCalendars="availableCalendars"
                        :defaultCalendarId="defaultCalendarId"
                        :visibleCalendarIds="visibleCalendarIds"
                        :localTimezone="localTimezone"
                        @update:activeProfile="activeProfile = $event"
                        @update:defaultCalendar="defaultCalendarId = $event"
                        @toggle-calendar="toggleCalendar"
                        @reorder-calendars="reorderCalendars"
                        @range-changed="(r) => fetchEvents(r.start, r.end)"
                        @add-app="(idx) => {
                            addingToSlotIndex = idx;
                            activeTab = 'other';
                        }"
                        @close-edit="() => {
                            editingWorkspaceId = null;
                            addingToSlotIndex = null;
                        }"
                        @add-slot="() => {
                            const current = workspace.layout || 'full';
                            let next = current;
                            if (current === 'full') next = 'split-horizontal';
                            else if (current === 'split-horizontal' || current === 'split-vertical') next = 'sidebar-right';
                            else if (current === 'sidebar-right' || current === 'sidebar-left') next = 'grid-2x2';
                            
                            if (next !== current) {
                                const slotsMap = { 'full': 1, 'split-vertical': 2, 'split-horizontal': 2, 'sidebar-right': 3, 'sidebar-left': 3, 'grid-2x2': 4 };
                                const targetSlots = slotsMap[next];
                                const newApps = [...workspace.apps];
                                while (newApps.length < targetSlots) newApps.push(null);
                                updateWorkspace(workspace.id, { layout: next, apps: newApps });
                            }
                        }"
                        @remove-app="(idx) => {
                            const w = { ...workspace, apps: [...workspace.apps] };
                            w.apps[idx] = null;
                            updateWorkspace(workspace.id, { apps: w.apps });
                        }"
                        @swap-apps="(idx) => {
                            const w = { ...workspace, apps: [...workspace.apps] };
                            const nextIdx = (idx + 1) % w.apps.length;
                            const temp = w.apps[idx];
                            w.apps[idx] = w.apps[nextIdx];
                            w.apps[nextIdx] = temp;
                            updateWorkspace(workspace.id, { apps: w.apps });
                        }"
                        @rename-workspace="() => {
                            const newName = prompt('Enter a new name for this workspace:', workspace.name);
                            if (newName) updateWorkspace(workspace.id, { name: newName });
                        }"
                        @cycle-layout="() => {
                            const current = workspace.layout || 'full';
                            const order = ['full', 'split-vertical', 'split-horizontal', 'sidebar-right', 'sidebar-left', 'grid-2x2'];
                            const next = order[(order.indexOf(current) + 1) % order.length];
                            
                            const slotsMap = { 'full': 1, 'split-vertical': 2, 'split-horizontal': 2, 'sidebar-right': 3, 'sidebar-left': 3, 'grid-2x2': 4 };
                            const targetSlots = slotsMap[next];
                            
                            const newApps = [...workspace.apps];
                            while (newApps.length < targetSlots) newApps.push(null);
                            if (newApps.length > targetSlots) newApps.length = targetSlots;
                            
                            updateWorkspace(workspace.id, { layout: next, apps: newApps });
                        }"
                    />
                </TabsContent>

                <!-- Unpinned App View -->
                <TabsContent
                    value="unpinned"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <WorkspaceView
                        v-if="unpinnedAppId && unpinnedAppId !== 'aura'"
                        :workspace="{ id: 'unpinned', name: 'Temporary', layout: 'full', apps: [unpinnedAppId] }"
                        :is-editing="false"
                        :events="filteredEvents"
                        :scheduleEvents="filteredScheduleEvents"
                        :activeProfile="activeProfile"
                        :profiles="profiles"
                        :availableCalendars="availableCalendars"
                        :defaultCalendarId="defaultCalendarId"
                        :visibleCalendarIds="visibleCalendarIds"
                        :localTimezone="localTimezone"
                        @update:activeProfile="activeProfile = $event"
                        @update:defaultCalendar="defaultCalendarId = $event"
                        @toggle-calendar="toggleCalendar"
                        @reorder-calendars="reorderCalendars"
                        @range-changed="(r) => fetchEvents(r.start, r.end)"
                    />
                </TabsContent>

                <TabsContent
                    value="other"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <OtherTab 
                        :workspaces="workspaces" 
                        @create-workspace="(appId) => {
                            const newWs = createWorkspace(appId);
                            activeTab = newWs.id;
                        }"
                        @remove-workspace="removeWorkspace"
                        @launch="handleAppLaunch"
                    />
                </TabsContent>
            </div>
        </Tabs>

        <!-- Settings Dialog Component -->
        <SettingsDialog
            v-model:open="isSettingsDialogOpen"
            v-model:localTimezone="localTimezone"
            v-model:themePreference="themePreference"
            v-model:defaultRadarLayers="defaultRadarLayers"
            v-model:developerSettings="developerSettings"
            :isSyncing="isSyncing"
            @open-sync="handleOpenSync"
            @update:localTimezone="saveFilters"
        />

        <!-- Smart Sync Selection Dialog Component -->
        <SyncDialog v-model:open="isSyncModalOpen" @sync="handleSyncRequest" />

        <!-- Mute Weather Alerts Dialog -->
        <Dialog v-model:open="isMuteModalOpen">
            <DialogContent class="max-w-md rounded-[2.5rem] border-none bg-white/90 p-8 shadow-2xl backdrop-blur-2xl dark:bg-[#111]/90">
                <DialogHeader>
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-500/10 text-red-500">
                        <BellOff class="h-10 w-10" />
                    </div>
                    <DialogTitle class="text-3xl font-black tracking-tight uppercase">Mute Alerts</DialogTitle>
                    <DialogDescription class="text-foreground/80 mt-2 text-lg font-bold">
                        How long would you like to hide weather alert banners for? (You can still see them in the Weather tab).
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-6 flex flex-col gap-3">
                    <Button variant="outline" class="h-14 rounded-2xl border-2 font-bold text-lg justify-start px-6" @click="muteBanner(30)">
                        For 30 Minutes
                    </Button>
                    <Button variant="outline" class="h-14 rounded-2xl border-2 font-bold text-lg justify-start px-6" @click="muteBanner(60)">
                        For 1 Hour
                    </Button>
                    <Button variant="outline" class="h-14 rounded-2xl border-2 font-bold text-lg justify-start px-6" @click="muteBanner(180)">
                        For 3 Hours
                    </Button>
                    <Button variant="outline" class="h-14 rounded-2xl border-2 font-bold text-lg justify-start px-6 text-red-500 border-red-500/20 hover:bg-red-500/10" @click="muteBanner(1440)">
                        For 24 Hours
                    </Button>
                </div>

                <div class="mt-8 flex justify-end">
                    <Button @click="isMuteModalOpen = false" variant="ghost" class="h-12 rounded-xl px-8 font-bold">Cancel</Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Global Virtual Keyboard -->
        <VirtualKeyboard />
    </div>
</template>

<style scoped>
@keyframes marquee {
    0% { transform: translateX(100vw); }
    100% { transform: translateX(-100%); }
}
.animate-marquee {
    animation: marquee 25s linear infinite;
    will-change: transform;
}

:deep(.fc) {
    --fc-page-bg-color: transparent;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground) / 0.1);
    border-radius: 20px;
    border: 4px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.3);
    background-clip: content-box;
}
</style>
