<script setup>
import { Tabs, TabsContent } from '@/components/ui/tabs'
import { Home, User } from 'lucide-vue-next'
import CalendarTab from './CalendarTab.vue'
import RecipeBrowser from './recipes/RecipeBrowser.vue'
import ShoppingList from './shopping/ShoppingList.vue'
import WeatherTab from './WeatherTab.vue'

// Extracted Subcomponents & Composable
import { useDashboard } from './dashboard/useDashboard'
import DashboardHeader from './dashboard/DashboardHeader.vue'
import SettingsDialog from './dashboard/SettingsDialog.vue'
import SyncDialog from './dashboard/SyncDialog.vue'

const {
    isEditMode,
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
        class="flex h-screen max-h-screen flex-col overflow-hidden bg-[#f2f2f7] p-4 font-sans transition-colors duration-500 lg:p-6 dark:bg-[#000000]"
    >
        <Tabs default-value="family" class="flex min-h-0 flex-1 flex-col gap-6">
            <!-- Header Island Component -->
            <DashboardHeader @open-settings="isSettingsDialogOpen = true" />

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
                        :visibleCalendarIds="visibleCalendarIds"
                        @update:activeProfile="activeProfile = $event"
                        @toggle-calendar="toggleCalendar"
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
            </div>
        </Tabs>

        <!-- Settings Dialog Component -->
        <SettingsDialog
            v-model:open="isSettingsDialogOpen"
            v-model:isEditMode="isEditMode"
            :isSyncing="isSyncing"
            @open-sync="handleOpenSync"
        />

        <!-- Smart Sync Selection Dialog Component -->
        <SyncDialog v-model:open="isSyncModalOpen" @sync="handleSyncRequest" />
    </div>
</template>

<style scoped>
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
