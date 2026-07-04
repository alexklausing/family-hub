<script setup>
import { onMounted, onUnmounted, ref, computed, watch, provide } from 'vue'
import { Tabs, TabsContent } from '@/components/ui/tabs'
import { Home, User, AlertTriangle, X, BellOff } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import CalendarTab from './CalendarTab.vue'
import RecipeBrowser from './recipes/RecipeBrowser.vue'
import ShoppingList from './shopping/ShoppingList.vue'
import WeatherTab from './WeatherTab.vue'
import ChoresTab from './chores/ChoresTab.vue'

// Extracted Subcomponents & Composable
import { useDashboard } from './dashboard/useDashboard'
import DashboardHeader from './dashboard/DashboardHeader.vue'
import SettingsDialog from './dashboard/SettingsDialog.vue'
import SyncDialog from './dashboard/SyncDialog.vue'
import { useWeather } from '@/composables/useWeather'

const { alerts, fetchWeather, weatherData } = useWeather()

const activeTab = ref('family')
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

const developerSettings = ref(JSON.parse(localStorage.getItem('developerSettings') || 'null') || {
    masterToggle: false,
    testWeatherAlerts: false,
})
provide('developerSettings', developerSettings)

watch(developerSettings, (newVal) => {
    localStorage.setItem('developerSettings', JSON.stringify(newVal))
    fetchWeather(true) // Refresh weather when developer settings change
}, { deep: true })

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
} = useDashboard()

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
</script>

<template>
    <div
        class="flex h-screen max-h-screen flex-col overflow-hidden bg-[#f2f2f7] text-black dark:text-white p-4 font-sans transition-colors duration-500 lg:p-6 dark:bg-[#000000]"
    >
        <Tabs v-model="activeTab" class="flex min-h-0 flex-1 flex-col gap-6">
            <!-- Header Island Component -->
            <DashboardHeader :active-tab="activeTab" @open-settings="isSettingsDialogOpen = true" />

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
            <div class="relative min-h-0 flex-1 overflow-hidden">
                <!-- Dynamic Content -->
                <TabsContent
                    value="family"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <CalendarTab
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
                    value="weather"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <WeatherTab />
                </TabsContent>

                <TabsContent
                    value="recipes"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <RecipeBrowser />
                </TabsContent>

                <TabsContent
                    value="shopping"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <ShoppingList />
                </TabsContent>

                <TabsContent
                    value="chores"
                    class="m-0 h-full p-0 focus-visible:ring-0"
                >
                    <ChoresTab
                        :profiles="profiles"
                        :activeProfile="activeProfile"
                        @update:activeProfile="activeProfile = $event"
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
