<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

import {
    Clock,
    ArrowRight,
    ChevronRight,
    ChevronLeft,
    PanelRightClose,
    PanelRightOpen,
    Calendar as CalendarIcon,
    History,
    Filter,
    User,
    X,
} from 'lucide-vue-next'

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    scheduleEvents: {
        type: Array,
        default: () => [],
    },
    activeProfile: {
        type: String,
        default: 'Family',
    },
    profiles: {
        type: Array,
        default: () => [],
    },
    availableCalendars: {
        type: Array,
        default: () => [],
    },
    visibleCalendarIds: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'range-changed',
    'update:activeProfile',
    'toggle-calendar',
])

// Sidebar State
const isSidebarOpen = ref(true)
const isUserDrawerOpen = ref(false)
const isFilterDialogOpen = ref(false)

// Long Press Logic
let longPressTimer = null
const handleTouchStart = () => {
    longPressTimer = setTimeout(() => {
        isFilterDialogOpen.value = true
        longPressTimer = null
    }, 600)
}

const handleTouchEnd = () => {
    if (longPressTimer) {
        clearTimeout(longPressTimer)
        longPressTimer = null
        isUserDrawerOpen.value = true
    }
}

// Schedule Events Logic
const sortedSchedule = computed(() => {
    const now = new Date()
    return [...props.scheduleEvents]
        .filter((e) => new Date(e.end || e.start) >= now)
        .sort((a, b) => new Date(a.start) - new Date(b.start))
        .slice(0, 50)
})

// Modal State
const isModalOpen = ref(false)
const modalMode = ref('create') // 'create' or 'edit'
const selectedEvent = ref({
    id: null,
    title: '',
    start: '',
    end: '',
    allDay: false,
})

// Calendar Configuration
const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'twoWeekView',
    views: {
        twoWeekView: {
            type: 'dayGrid',
            duration: { weeks: 2 },
            buttonText: '2 Weeks',
        },
        dayGridMonth: {
            buttonText: 'Month',
            dayMaxEvents: false,
        },
        dayGridDay: {
            buttonText: 'Day',
            dayMaxEvents: false,
        },
        timeGridWeek: {
            dayMaxEvents: false,
            scrollTime: new Date().getHours() + ':00:00', // Center on current hour
            slotMinTime: '06:00:00', // Hide early morning
            slotMaxTime: '23:00:00', // Hide late night
        },
        timeGridDay: {
            dayMaxEvents: false,
            scrollTime: new Date().getHours() + ':00:00',
            slotMinTime: '06:00:00',
            slotMaxTime: '23:00:00',
        },
    },
    headerToolbar: false, // We build our own
    events: [],
    dayMaxEvents: false,
    selectable: true,
    selectMirror: true,
    editable: false,

    eventContent: (arg) => {
        const timeText = arg.timeText
        const title = arg.event.title
        const isAllDay = arg.event.allDay

        const container = document.createElement('div')
        container.className =
            'flex flex-col gap-0.5 leading-tight overflow-hidden py-0.5 px-1'

        if (!isAllDay && timeText) {
            const timeEl = document.createElement('div')
            timeEl.className =
                'text-[9px] font-black uppercase tracking-tighter opacity-80 mb-0.5'
            timeEl.innerText = timeText
            container.appendChild(timeEl)
        }

        const titleEl = document.createElement('div')
        titleEl.className = 'font-bold text-[10px] truncate'
        titleEl.innerText = title
        container.appendChild(titleEl)

        return { domNodes: [container] }
    },

    select: handleDateSelect,
    eventClick: handleEventClick,
    datesSet: handleDatesSet,
})

watch(
    () => props.events,
    (newEvents) => {
        calendarOptions.value.events = newEvents.map((event) => ({
            id: String(event.id),
            title: event.title,
            start: event.start,
            end: event.end,
            allDay: event.all_day,
            backgroundColor: event.calendar?.color || '#3b82f6',
            borderColor: event.calendar?.color || '#3b82f6',
            extendedProps: {
                provider: event.calendar?.provider,
                calendarName: event.calendar?.name,
            },
        }))
    },
    { immediate: true },
)

const fullCalendar = ref(null)
const currentViewTitle = ref('')

const next = () => fullCalendar.value.getApi().next()
const prev = () => fullCalendar.value.getApi().prev()
const today = () => fullCalendar.value.getApi().today()
const changeView = (view) => fullCalendar.value.getApi().changeView(view)

const selectProfile = (name) => {
    emit('update:activeProfile', name)
    isUserDrawerOpen.value = false
}

function handleDatesSet(info) {
    currentViewTitle.value = info.view.title
    emit('range-changed', { start: info.startStr, end: info.endStr })
}

function handleDateSelect(selectInfo) {
    modalMode.value = 'create'
    selectedEvent.value = {
        id: null,
        title: '',
        start: selectInfo.startStr,
        end: selectInfo.endStr,
        allDay: selectInfo.allDay,
    }
    isModalOpen.value = true
}

function handleEventClick(clickInfo) {
    modalMode.value = 'edit'
    selectedEvent.value = {
        id: clickInfo.event.id,
        title: clickInfo.event.title,
        start: clickInfo.event.startStr,
        end: clickInfo.event.endStr,
        allDay: clickInfo.event.allDay,
    }
    isModalOpen.value = true
}

function saveEvent() {
    console.log('Saving event:', selectedEvent.value)
    isModalOpen.value = false
}
</script>

<template>
    <div class="relative flex h-full w-full flex-col gap-4 overflow-hidden">
        <!-- User Slide-out Drawer (Left) -->
        <Transition
            enter-active-class="transition-transform duration-500 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-500 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div
                v-if="isUserDrawerOpen"
                class="absolute inset-y-0 left-0 z-[2000] flex w-80 flex-col gap-8 border-r border-white/10 bg-white/90 p-8 shadow-2xl backdrop-blur-3xl dark:bg-black/90"
            >
                <div class="flex items-center justify-between">
                    <h3
                        class="text-3xl font-black tracking-tighter uppercase italic"
                    >
                        Profiles
                    </h3>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="isUserDrawerOpen = false"
                        class="h-12 w-12 rounded-full"
                    >
                        <X class="h-6 w-6" />
                    </Button>
                </div>

                <div class="flex flex-col gap-4">
                    <div
                        v-for="profile in profiles"
                        :key="profile.name"
                        @click="selectProfile(profile.name)"
                        :class="[
                            'flex cursor-pointer items-center gap-5 rounded-[2rem] border-none p-6 transition-all select-none',
                            activeProfile === profile.name
                                ? 'bg-primary shadow-primary/20 scale-105 text-white shadow-xl'
                                : 'hover:bg-accent/50 bg-white/20 dark:bg-white/5',
                        ]"
                    >
                        <div
                            :class="[
                                'flex h-12 w-12 items-center justify-center rounded-2xl',
                                activeProfile === profile.name
                                    ? 'bg-white/20'
                                    : 'bg-primary/10',
                            ]"
                        >
                            <component :is="profile.icon" class="h-7 w-7" />
                        </div>
                        <span class="text-xl font-black tracking-tight">{{
                            profile.name
                        }}</span>
                        <ChevronRight
                            v-if="activeProfile === profile.name"
                            class="ml-auto h-6 w-6 opacity-60"
                        />
                    </div>
                </div>

                <div
                    class="bg-muted/30 mt-auto rounded-[2rem] border border-white/5 p-6 text-center text-sm italic opacity-40"
                >
                    Switch profiles to filter calendar views.
                </div>
            </div>
        </Transition>

        <!-- Drawer Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-500"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-500"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isUserDrawerOpen"
                @click="isUserDrawerOpen = false"
                class="absolute inset-0 z-[1900] bg-black/40 backdrop-blur-md"
            ></div>
        </Transition>

        <!-- Slim Toolbar -->
        <div class="flex shrink-0 items-center justify-between px-2">
            <div class="flex items-center gap-4">
                <!-- User Button replaces static Title -->
                <Button
                    variant="ghost"
                    class="flex h-14 items-center gap-4 rounded-2xl border border-white/10 bg-white/40 px-6 shadow-none backdrop-blur-2xl transition-all select-none hover:bg-white/60 active:scale-95 dark:bg-white/5"
                    @mousedown="handleTouchStart"
                    @mouseup="handleTouchEnd"
                    @mouseleave="handleTouchEnd"
                    @touchstart.passive="handleTouchStart"
                    @touchend.passive="handleTouchEnd"
                >
                    <div
                        class="bg-primary/10 pointer-events-none flex h-8 w-8 items-center justify-center rounded-lg"
                    >
                        <component
                            :is="
                                profiles.find((p) => p.name === activeProfile)
                                    ?.icon || User
                            "
                            class="text-primary h-5 w-5"
                        />
                    </div>
                    <span
                        class="pointer-events-none text-lg font-black tracking-tight uppercase italic"
                    >
                        {{
                            activeProfile === 'Family'
                                ? 'Family Hub'
                                : activeProfile + "'s"
                        }}
                        Calendar
                    </span>
                </Button>

                <div
                    class="flex items-center gap-1 rounded-2xl border border-white/10 bg-white/20 p-1 backdrop-blur-xl dark:bg-white/5"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="prev"
                        class="h-10 w-10 rounded-xl"
                        ><ChevronLeft class="h-5 w-5"
                    /></Button>
                    <Button
                        variant="ghost"
                        @click="today"
                        class="h-10 rounded-xl px-4 text-[10px] font-black tracking-widest uppercase"
                        >Today</Button
                    >
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="next"
                        class="h-10 w-10 rounded-xl"
                        ><ChevronRight class="h-5 w-5"
                    /></Button>
                </div>

                <div
                    class="ml-2 text-lg font-black tracking-tight uppercase tabular-nums opacity-40"
                >
                    {{ currentViewTitle }}
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div
                    class="flex items-center gap-1 rounded-2xl border border-white/10 bg-white/20 p-1 backdrop-blur-xl dark:bg-white/5"
                >
                    <Button
                        v-for="view in [
                            { id: 'dayGridMonth', label: 'Month' },
                            { id: 'twoWeekView', label: '2 Weeks' },
                            { id: 'timeGridWeek', label: 'Week' },
                            { id: 'timeGridDay', label: 'Day' },
                        ]"
                        :key="view.id"
                        variant="ghost"
                        @click="changeView(view.id)"
                        class="h-10 rounded-xl px-4 text-[10px] font-black tracking-widest uppercase"
                    >
                        {{ view.label }}
                    </Button>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    @click="isSidebarOpen = !isSidebarOpen"
                    :class="[
                        'h-12 w-12 rounded-2xl transition-all',
                        isSidebarOpen
                            ? 'bg-primary text-white'
                            : 'border border-white/10 bg-white/20 dark:bg-white/5',
                    ]"
                >
                    <component
                        :is="isSidebarOpen ? PanelRightClose : PanelRightOpen"
                        class="h-6 w-6"
                    />
                </Button>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="flex min-h-0 flex-1 gap-6">
            <!-- Calendar Area -->
            <Card
                class="flex-1 overflow-hidden rounded-[2.5rem] border-none bg-white/60 shadow-none backdrop-blur-3xl dark:bg-white/5"
            >
                <CardContent class="h-full p-6">
                    <FullCalendar
                        ref="fullCalendar"
                        :options="calendarOptions"
                        class="h-full"
                    />
                </CardContent>
            </Card>

            <!-- Collapsible Up Next Sidebar -->
            <Transition
                enter-active-class="transition-all duration-500 ease-out"
                enter-from-class="translate-x-full opacity-0 w-0 mr-[-1.5rem]"
                enter-to-class="translate-x-0 opacity-100 w-80 mr-0"
                leave-active-class="transition-all duration-500 ease-in"
                leave-from-class="translate-x-0 opacity-100 w-80 mr-0"
                leave-to-class="translate-x-full opacity-0 w-0 mr-[-1.5rem]"
            >
                <div
                    v-if="isSidebarOpen"
                    class="flex min-h-0 w-80 shrink-0 flex-col gap-4"
                >
                    <Card
                        class="flex flex-1 flex-col overflow-hidden rounded-[2.5rem] border-none bg-white/60 shadow-none backdrop-blur-3xl dark:bg-white/5"
                    >
                        <CardHeader class="shrink-0 p-8 pb-4">
                            <CardTitle
                                class="text-primary flex items-center gap-3 text-2xl font-black tracking-tight uppercase italic"
                            >
                                <Clock class="h-6 w-6" />
                                Up Next
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="custom-scrollbar flex-1 overflow-y-auto p-6 pt-2"
                        >
                            <div class="space-y-3">
                                <div
                                    v-if="sortedSchedule.length > 0"
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="event in sortedSchedule"
                                        :key="event.id"
                                        class="group relative flex cursor-pointer items-center gap-4 rounded-[1.5rem] bg-white/40 p-4 transition-all hover:bg-white/80 dark:bg-white/5 dark:hover:bg-white/10"
                                    >
                                        <div
                                            class="h-8 w-1 shrink-0 rounded-full"
                                            :style="{
                                                backgroundColor:
                                                    event.calendar?.color ||
                                                    '#3b82f6',
                                            }"
                                        ></div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm leading-tight font-black tracking-tight"
                                            >
                                                {{ event.title }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[9px] font-black tracking-widest uppercase opacity-50"
                                            >
                                                <span
                                                    v-if="
                                                        new Date(
                                                            event.start,
                                                        ).toDateString() ===
                                                        new Date().toDateString()
                                                    "
                                                    >Today</span
                                                >
                                                <span v-else>{{
                                                    new Date(
                                                        event.start,
                                                    ).toLocaleDateString([], {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    })
                                                }}</span>
                                                <span v-if="!event.all_day">
                                                    •
                                                    {{
                                                        new Date(
                                                            event.start,
                                                        ).toLocaleTimeString(
                                                            [],
                                                            {
                                                                hour: '2-digit',
                                                                minute: '2-digit',
                                                            },
                                                        )
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="py-12 text-center opacity-20"
                                >
                                    <History class="mx-auto mb-4 h-12 w-12" />
                                    <p class="text-sm font-bold uppercase">
                                        Clear Schedule
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </Transition>
        </div>

        <!-- Event Management Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent
                class="rounded-3xl border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[425px] dark:bg-black/95"
            >
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">{{
                        modalMode === 'create' ? 'Add New Event' : 'Edit Event'
                    }}</DialogTitle>
                    <DialogDescription
                        class="text-[10px] font-bold tracking-widest uppercase italic opacity-60"
                    >
                        {{
                            modalMode === 'create'
                                ? 'Enter the details for your new event.'
                                : 'Modify the details of this event.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-6 py-6">
                    <div class="flex flex-col gap-2">
                        <Label
                            for="title"
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Event Title</Label
                        >
                        <Input
                            id="title"
                            v-model="selectedEvent.title"
                            class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 text-lg font-bold"
                            placeholder="Dinner, Soccer, etc."
                        />
                    </div>
                </div>

                <DialogFooter class="gap-3">
                    <Button
                        variant="ghost"
                        @click="isModalOpen = false"
                        class="h-14 rounded-2xl text-xs font-black tracking-widest uppercase opacity-40 hover:opacity-100"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        @click="saveEvent"
                        class="h-14 rounded-2xl px-8 text-xs font-black tracking-widest uppercase shadow-none"
                        >Save Event</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Calendar Selection Modal (Restored) -->
        <Dialog v-model:open="isFilterDialogOpen">
            <DialogContent
                class="rounded-[3rem] border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[500px] dark:bg-black/95"
            >
                <DialogHeader class="mb-6">
                    <div class="mb-2 flex items-center gap-4">
                        <div
                            class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-2xl"
                        >
                            <Filter class="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-2xl font-black uppercase italic"
                                >Calendar Filters</DialogTitle
                            >
                            <DialogDescription
                                class="text-[10px] font-bold tracking-widest uppercase opacity-40"
                            >
                                Customize view for {{ activeProfile }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div
                    class="custom-scrollbar max-h-[400px] space-y-4 overflow-y-auto pr-2"
                >
                    <div
                        v-for="calendar in availableCalendars"
                        :key="calendar.id"
                        class="flex items-center justify-between rounded-[2rem] border border-white/5 bg-white/40 p-6 shadow-sm transition-all dark:bg-white/5"
                    >
                        <div class="flex items-center gap-5">
                            <div
                                class="h-4 w-4 rounded-full shadow-lg"
                                :style="{ backgroundColor: calendar.color }"
                            ></div>
                            <div>
                                <h4 class="text-xl font-black tracking-tight">
                                    {{ calendar.name }}
                                </h4>
                                <p
                                    class="text-[10px] font-bold tracking-widest uppercase italic opacity-40"
                                >
                                    {{ calendar.provider }} feed
                                </p>
                            </div>
                        </div>
                        <Switch
                            :checked="visibleCalendarIds.includes(calendar.id)"
                            @update:checked="
                                emit('toggle-calendar', calendar.id)
                            "
                            class="scale-150"
                        />
                    </div>
                </div>

                <div
                    class="bg-primary/5 border-primary/10 mt-8 rounded-[2rem] border p-6 text-center text-[10px] font-bold tracking-widest uppercase italic opacity-60"
                >
                    Enabled calendars will layer together in your view.
                </div>

                <div class="mt-6">
                    <Button
                        class="h-16 w-full rounded-2xl text-sm font-black tracking-widest uppercase shadow-none"
                        @click="isFilterDialogOpen = false"
                        >Save View</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* Adjust FullCalendar for the new embedded layout */
.fc {
    --fc-border-color: rgba(255, 255, 255, 0.05);
    --fc-today-bg-color: rgba(59, 130, 246, 0.05);
    font-family: inherit !important;
}
.fc .fc-toolbar {
    display: none !important;
}
.fc-theme-standard .fc-scrollgrid {
    border: none !important;
    border-radius: 2rem;
    overflow: hidden;
}
.fc-theme-standard td,
.fc-theme-standard th {
    border-color: var(--fc-border-color);
}
.fc-col-header-cell {
    padding: 1rem 0 !important;
}
.fc-col-header-cell-cushion {
    font-weight: 900 !important;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-size: 0.75rem;
    opacity: 0.4;
}
.fc-daygrid-day-number {
    font-weight: 900 !important;
    padding: 1rem !important;
    opacity: 0.8;
    font-size: 1rem;
}
.fc-daygrid-event {
    border-radius: 0.75rem !important;
    padding: 0.25rem 0.5rem !important;
    margin: 2px 4px !important;
    border: none !important;
}
.fc-event-title {
    font-weight: 900 !important;
    font-size: 0.75rem !important;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.fc-day-today {
    background-color: var(--fc-today-bg-color) !important;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
}
</style>
