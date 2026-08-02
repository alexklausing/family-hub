<script setup>
import CalendarTab from './CalendarTab.vue'
import RecipeBrowser from './recipes/RecipeBrowser.vue'
import ShoppingList from './shopping/ShoppingList.vue'
import WeatherTab from './WeatherTab.vue'
import ChoresTab from './chores/ChoresTab.vue'
import AuraTab from './AuraTab.vue'
import CountdownWidget from './CountdownWidget.vue'
import UpNextWidget from './UpNextWidget.vue'
import WordOfTheDayWidget from './WordOfTheDayWidget.vue'
import CelebrationTab from './CelebrationTab.vue'

const props = defineProps({
    appId: {
        type: String,
        required: true
    },
    // Dashboard Global State/Props
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
    'range-changed'
])
</script>

<template>
    <div class="w-full h-full relative overflow-hidden bg-white dark:bg-black rounded-3xl shadow-sm">
        <CalendarTab
            v-if="appId === 'family'"
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
        
        <WeatherTab v-else-if="appId === 'weather'" />
        
        <RecipeBrowser v-else-if="appId === 'recipes'" />
        
        <ShoppingList v-else-if="appId === 'shopping'" />
        
        <ChoresTab
            v-else-if="appId === 'chores'"
            :profiles="profiles"
            :activeProfile="activeProfile"
            @update:activeProfile="emit('update:activeProfile', $event)"
        />
        
        <AuraTab v-else-if="appId === 'aura'" />
        
        <CountdownWidget v-else-if="appId === 'countdowns'" class="h-full w-full" />
        
        <UpNextWidget v-else-if="appId === 'up-next'" :scheduleEvents="scheduleEvents" class="h-full w-full" />

        <WordOfTheDayWidget v-else-if="appId === 'word-of-the-day'" />

        <CelebrationTab v-else-if="appId === 'celebrations'" />
        
        <div v-else class="w-full h-full flex items-center justify-center bg-slate-100 dark:bg-slate-900 text-slate-500">
            Unknown App: {{ appId }}
        </div>
    </div>
</template>
