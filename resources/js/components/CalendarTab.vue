<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import draggable from 'vuedraggable'
import axios from 'axios'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import luxonPlugin from '@fullcalendar/luxon3'

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
    Plus,
    Trash2,
    Loader2,
    Pencil,
    RefreshCw,
    Star,
    GripVertical,
    EyeOff,
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
        required: true,
    },
    visibleCalendarIds: {
        type: Array,
        required: true,
    },
    defaultCalendarId: {
        type: Number,
        default: null,
    },
    localTimezone: {
        type: String,
        default: 'local',
    },
})

const emit = defineEmits([
    'range-changed',
    'update:activeProfile',
    'update:defaultCalendar',
    'reorder-calendars',
    'toggle-calendar',
])

// Sidebar State
const isSidebarOpen = ref(true)
const isUserDrawerOpen = ref(false)
const isFilterDialogOpen = ref(false)
const isImportCalendarModalOpen = ref(false)
const importModalMode = ref('create')
const editingCalendarId = ref(null)

const getRandomColor = () => {
    const colors = [
        '#dc2626',
        '#d97706',
        '#65a30d',
        '#059669',
        '#0284c7',
        '#4f46e5',
        '#9333ea',
        '#e11d48',
    ]
    return colors[Math.floor(Math.random() * colors.length)]
}

const importForm = ref({
    provider: 'ical',
    name: '',
    url: '',
    email: '',
    password: '',
    apple_calendar_path: '',
    color: getRandomColor(),
})
const isImporting = ref(false)
const isFetchingAppleCalendars = ref(false)
const availableAppleCalendars = ref([])
const importError = ref('')

// Long Press logic has been replaced with native @contextmenu.prevent for better touchscreen support

// Schedule Events Logic
const getEventDate = (event, isEnd = false) => {
    const dateStr = isEnd && event.end ? event.end : event.start
    if (event.all_day) {
        // Extract YYYY-MM-DD and parse as local midnight
        const parts = dateStr.substring(0, 10).split('-')
        return new Date(parts[0], parts[1] - 1, parts[2])
    }
    return new Date(dateStr)
}

const sortedSchedule = computed(() => {
    const now = new Date()
    return [...props.scheduleEvents]
        .filter((e) => {
            const start = getEventDate(e)
            const end = getEventDate(e, true)
            return (
                end >= now ||
                (e.all_day && start.toDateString() === now.toDateString())
            )
        })
        .sort((a, b) => getEventDate(a) - getEventDate(b))
        .slice(0, 50)
})

// Modal State
const isModalOpen = ref(false)
const modalMode = ref('create') // 'create' or 'edit'
const selectedEvent = ref({
    id: null,
    calendar_id: null,
    title: '',
    description: '',
    startDate: '',
    startTime: '12:00',
    endDate: '',
    endTime: '13:00',
    allDay: false,
    isReadOnly: false,
})

const isSavingEvent = ref(false)
const eventError = ref('')

const writableCalendars = computed(() => {
    return props.availableCalendars.filter((c) => c.provider === 'apple')
})

const localCalendars = ref([...props.availableCalendars])

watch(
    () => props.availableCalendars,
    (newVals) => {
        localCalendars.value = [...newVals]
    },
    { deep: true },
)

// Calendar Configuration
const calendarOptions = ref({
    timeZone: props.localTimezone,
    plugins: [luxonPlugin, dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
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
        listWeek: {
            buttonText: 'Agenda',
        },
    },
    headerToolbar: false, // We build our own
    events: [],
    displayEventTime: true,
    eventTimeFormat: {
        hour: 'numeric',
        minute: '2-digit',
        meridiem: 'short',
    },
    dayMaxEvents: false,
    eventDisplay: 'block',
    selectable: true,
    selectMirror: true,
    editable: false,
    eventOrder: 'start,-duration,allDay,calendarOrder,title',

    eventContent: (arg) => {
        const timeText = arg.timeText
        const title = arg.event.title
        const isAllDay = arg.event.allDay

        const container = document.createElement('div')
        container.className =
            'flex flex-col gap-0.5 leading-tight overflow-hidden py-0.5 px-1 text-white'

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
    dateClick: handleDateClick,
    eventClick: handleEventClick,
    datesSet: handleDatesSet,
})

watch(
    () => props.localTimezone,
    (newTz) => {
        calendarOptions.value.timeZone = newTz
    },
)

watch(isSidebarOpen, () => {
    // Force FullCalendar to recalculate its grid width during the 500ms CSS transition
    const fc = fullCalendar.value?.getApi()
    if (fc) {
        const start = Date.now()
        const animate = () => {
            fc.updateSize()
            if (Date.now() - start < 550) {
                requestAnimationFrame(animate)
            }
        }
        requestAnimationFrame(animate)
    }
})

watch(
    () => [props.events, props.availableCalendars],
    ([newEvents, newCalendars]) => {
        calendarOptions.value.events = newEvents.map((event) => {
            const orderIdx = newCalendars.findIndex(
                (c) => c.id == event.calendar_id,
            )
            return {
                id: String(event.id),
                title: event.title,
                start: event.start,
                end: event.end,
                allDay: event.all_day,
                calendarOrder: orderIdx === -1 ? 999 : orderIdx,
                backgroundColor: event.calendar?.color || '#3b82f6',
                borderColor: event.calendar?.color || '#3b82f6',
                extendedProps: {
                    provider: event.calendar?.provider,
                    calendarName: event.calendar?.name,
                    calendar_id: event.calendar_id,
                },
            }
        })
    },
    { immediate: true, deep: true },
)

const fullCalendar = ref(null)
const currentViewTitle = ref('')
const currentViewType = ref('')

const next = () => fullCalendar.value.getApi().next()
const prev = () => fullCalendar.value.getApi().prev()
const today = () => fullCalendar.value.getApi().today()
const changeView = (view) => fullCalendar.value.getApi().changeView(view)

const todayButtonLabel = computed(() => {
    switch (currentViewType.value) {
        case 'dayGridMonth': return 'This Month';
        case 'timeGridWeek':
        case 'listWeek': return 'This Week';
        case 'twoWeekView': return 'Current';
        case 'timeGridDay':
        default: return 'Today';
    }
})

const selectProfile = (name) => {
    emit('update:activeProfile', name)
    isUserDrawerOpen.value = false
}

function handleDatesSet(info) {
    currentViewTitle.value = info.view.title
    currentViewType.value = info.view.type
    emit('range-changed', { start: info.startStr, end: info.endStr })
}

function parseLocalParts(isoString) {
    if (!isoString) return { date: '', time: '12:00' }

    // If it's just a date string from FullCalendar (e.g. "2026-06-19")
    if (isoString.length === 10) {
        return { date: isoString, time: '12:00' }
    }

    const date = new Date(isoString)
    if (isNaN(date.getTime())) return { date: '', time: '12:00' }

    const offset = date.getTimezoneOffset()
    const localDate = new Date(date.getTime() - offset * 60 * 1000)
    const iso = localDate.toISOString() // "2026-06-19T23:00:00.000Z"

    return {
        date: iso.slice(0, 10),
        time: iso.slice(11, 16),
    }
}

function handleDateSelect(selectInfo) {
    modalMode.value = 'create'
    const startParts = parseLocalParts(selectInfo.startStr)

    let endStrToParse = selectInfo.endStr
    if (selectInfo.allDay && endStrToParse && endStrToParse.length === 10) {
        const parts = endStrToParse.split('-')
        const d = new Date(parts[0], parts[1] - 1, parts[2])
        d.setDate(d.getDate() - 1)
        const y = d.getFullYear()
        const m = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        endStrToParse = `${y}-${m}-${day}`
    }
    const endParts = parseLocalParts(endStrToParse)

    // Determine default calendar
    let initialCalendarId = null
    if (writableCalendars.value.length > 0) {
        if (
            props.defaultCalendarId &&
            writableCalendars.value.some(
                (c) => c.id === props.defaultCalendarId,
            )
        ) {
            initialCalendarId = props.defaultCalendarId
        } else {
            initialCalendarId = writableCalendars.value[0].id
        }
    }

    selectedEvent.value = {
        id: null,
        calendar_id: initialCalendarId,
        title: '',
        description: '',
        startDate: startParts.date,
        startTime: selectInfo.allDay ? '09:00' : startParts.time,
        endDate: endParts.date,
        endTime: selectInfo.allDay ? '10:00' : endParts.time,
        allDay: false, // Force false by default as requested
        isReadOnly: false,
    }
    eventError.value = ''
    isModalOpen.value = true
}

function handleDateClick(info) {
    handleDateSelect({
        startStr: info.dateStr,
        endStr: info.dateStr,
        allDay: info.allDay
    })
}

function handleEventClick(clickInfo) {
    modalMode.value = 'edit'
    const startParts = parseLocalParts(clickInfo.event.startStr)
    const endParts = parseLocalParts(clickInfo.event.endStr)

    const provider = clickInfo.event.extendedProps.provider || 'ical'
    const isReadOnly = provider === 'ical'

    selectedEvent.value = {
        id: clickInfo.event.id,
        calendar_id: clickInfo.event.extendedProps.calendar_id || null,
        title: clickInfo.event.title,
        description: clickInfo.event.extendedProps.description || '',
        startDate: startParts.date,
        startTime: startParts.time,
        endDate: endParts.date,
        endTime: endParts.time,
        allDay: clickInfo.event.allDay,
        isReadOnly: isReadOnly,
    }
    eventError.value = ''
    isModalOpen.value = true
}

async function saveEvent() {
    if (!selectedEvent.value.title || !selectedEvent.value.calendar_id) {
        eventError.value = 'Title and Calendar are required.'
        return
    }

    isSavingEvent.value = true
    eventError.value = ''

    try {
        const startString = selectedEvent.value.allDay
            ? selectedEvent.value.startDate
            : `${selectedEvent.value.startDate}T${selectedEvent.value.startTime}:00`

        const endString = selectedEvent.value.allDay
            ? selectedEvent.value.endDate
            : `${selectedEvent.value.endDate}T${selectedEvent.value.endTime}:00`

        if (modalMode.value === 'create') {
            await axios.post(
                `/api/calendars/${selectedEvent.value.calendar_id}/events`,
                {
                    title: selectedEvent.value.title,
                    description: selectedEvent.value.description,
                    start: startString,
                    end: endString,
                    all_day: selectedEvent.value.allDay,
                    timezone: props.localTimezone,
                },
            )
        } else {
            // Edit not fully implemented for CalDAV yet, just show error or close
            console.log('Edit not implemented yet')
        }
        isModalOpen.value = false
        // Trigger sync
        emit('range-changed', {
            start: fullCalendar.value.getApi().view.activeStart.toISOString(),
            end: fullCalendar.value.getApi().view.activeEnd.toISOString(),
        })
    } catch (error) {
        eventError.value =
            error.response?.data?.error || 'Failed to save event.'
    } finally {
        isSavingEvent.value = false
    }
}

const isSettingDefault = ref(false)
const setDefaultCalendar = async (calendarId) => {
    isSettingDefault.value = true
    try {
        const response = await axios.post(
            `/api/profiles/${props.activeProfile}/default-calendar`,
            {
                calendar_id: calendarId,
            },
        )
        emit('update:defaultCalendar', response.data.default_calendar_id)
    } catch (error) {
        console.error('Failed to set default calendar:', error)
    } finally {
        isSettingDefault.value = false
    }
}

const openImportModal = () => {
    importModalMode.value = 'create'
    editingCalendarId.value = null
    importForm.value = {
        provider: 'ical',
        name: '',
        url: '',
        email: '',
        password: '',
        apple_calendar_path: '',
        color: getRandomColor(),
    }
    availableAppleCalendars.value = []
    importError.value = ''
    isImportCalendarModalOpen.value = true
}

const fetchAppleCalendars = async () => {
    isFetchingAppleCalendars.value = true
    importError.value = ''
    try {
        const response = await axios.post('/api/calendars/apple/fetch', {
            email: importForm.value.email,
            password: importForm.value.password,
        })
        availableAppleCalendars.value = response.data.calendars
        if (availableAppleCalendars.value.length > 0) {
            importForm.value.apple_calendar_path =
                availableAppleCalendars.value[0].path
            if (!importForm.value.name) {
                importForm.value.name = availableAppleCalendars.value[0].name
            }
        }
    } catch (error) {
        importError.value =
            error.response?.data?.errors?.password?.[0] ||
            'Failed to fetch calendars.'
    } finally {
        isFetchingAppleCalendars.value = false
    }
}

const openEditModal = (calendar) => {
    importModalMode.value = 'edit'
    editingCalendarId.value = calendar.id
    importForm.value = {
        provider: calendar.provider,
        name: calendar.name,
        url:
            calendar.provider === 'ical' ? calendar.credentials?.url || '' : '',
        email:
            calendar.provider === 'apple'
                ? calendar.credentials?.email || ''
                : '',
        password: '', // Left blank so user doesn't see password on edit
        apple_calendar_path:
            calendar.provider === 'apple'
                ? calendar.credentials?.path || ''
                : '',
        color: calendar.color || getRandomColor(),
    }
    availableAppleCalendars.value = []
    importError.value = ''
    isImportCalendarModalOpen.value = true
}

const submitImportCalendar = async () => {
    isImporting.value = true
    importError.value = ''
    try {
        const payload = {
            profile_name: props.activeProfile,
            name: importForm.value.name,
            provider: importForm.value.provider,
            color: importForm.value.color,
        }

        if (importForm.value.provider === 'ical') {
            payload.url = importForm.value.url
        } else if (importForm.value.provider === 'apple') {
            payload.email = importForm.value.email
            payload.apple_calendar_path = importForm.value.apple_calendar_path
            if (importForm.value.password) {
                payload.password = importForm.value.password
            }
        }

        if (importModalMode.value === 'edit') {
            await axios.put(
                `/api/calendars/${editingCalendarId.value}`,
                payload,
            )
        } else {
            await axios.post('/api/calendars', payload)
        }
        isImportCalendarModalOpen.value = false
        // Trigger a refresh of the events from the parent component
        emit('range-changed', {
            start: fullCalendar.value.getApi().view.activeStart.toISOString(),
            end: fullCalendar.value.getApi().view.activeEnd.toISOString(),
        })
    } catch (error) {
        importError.value =
            error.response?.data?.errors?.url?.[0] ||
            error.response?.data?.message ||
            'An unexpected error occurred.'
    } finally {
        isImporting.value = false
    }
}

const deleteCalendar = async (calendarId) => {
    if (!confirm('Are you sure you want to delete this calendar?')) return
    try {
        await axios.delete(`/api/calendars/${calendarId}`)
        // Refresh events
        emit('range-changed', {
            start: fullCalendar.value.getApi().view.activeStart.toISOString(),
            end: fullCalendar.value.getApi().view.activeEnd.toISOString(),
        })
    } catch (error) {
        console.error('Failed to delete calendar', error)
    }
}

// macOS native color picker click-away hack
const colorInputRef = ref(null)
const isColorPickerOpen = ref(false)

const openColorPicker = () => {
    isColorPickerOpen.value = true
}

const handleDocumentClick = (e) => {
    if (isColorPickerOpen.value && colorInputRef.value) {
        if (!colorInputRef.value.contains(e.target)) {
            // Force close the native picker
            colorInputRef.value.type = 'text'
            colorInputRef.value.type = 'color'
            isColorPickerOpen.value = false
        }
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
})

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick)
})
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

        <div class="flex shrink-0 items-center justify-between px-2">
            <div class="flex items-center gap-4">
                <!-- User Button replaces static Title -->
                <Button
                    variant="ghost"
                    class="flex h-14 items-center gap-4 rounded-2xl border border-white/10 bg-white/40 px-6 shadow-none backdrop-blur-2xl transition-all select-none hover:bg-white/60 active:scale-95 dark:bg-white/5"
                    @click="isUserDrawerOpen = true"
                    @contextmenu.prevent="isFilterDialogOpen = true"
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
                                ? 'Family'
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
                        >{{ todayButtonLabel }}</Button
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
                            { id: 'listWeek', label: 'Agenda' },
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
                            ? 'bg-black/80 text-white dark:bg-white/10 dark:text-white shadow-md'
                            : 'border border-black/5 dark:border-white/10 bg-white/40 dark:bg-white/5 hover:bg-white/60 dark:hover:bg-white/10',
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
        <div class="flex min-h-0 w-full flex-1">
            <!-- Calendar Area -->
            <Card
                class="w-full min-w-0 flex-1 overflow-hidden rounded-[2.5rem] border-none bg-white/60 shadow-none backdrop-blur-3xl dark:bg-white/5"
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
            <div
                class="flex shrink-0 overflow-hidden transition-all duration-500 ease-in-out"
                :class="
                    isSidebarOpen
                        ? 'ml-6 w-80 opacity-100'
                        : 'ml-0 w-0 opacity-0'
                "
            >
                <div class="flex min-h-0 w-80 shrink-0 flex-col gap-4">
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
                                                        getEventDate(
                                                            event,
                                                        ).toDateString() ===
                                                        new Date().toDateString()
                                                    "
                                                    >Today</span
                                                >
                                                <span v-else>{{
                                                    getEventDate(
                                                        event,
                                                    ).toLocaleDateString([], {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    })
                                                }}</span>
                                                <span v-if="!event.all_day">
                                                    •
                                                    {{
                                                        getEventDate(
                                                            event,
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
            </div>
        </div>

        <!-- Event Management Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent
                class="rounded-3xl border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[425px] dark:bg-black/95"
            >
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">
                        {{
                            modalMode === 'create'
                                ? 'Add New Event'
                                : selectedEvent.isReadOnly
                                  ? 'Event Details'
                                  : 'Edit Event'
                        }}
                    </DialogTitle>
                    <DialogDescription
                        class="text-[10px] font-bold tracking-widest uppercase italic opacity-60"
                    >
                        {{
                            modalMode === 'create'
                                ? 'Enter the details for your new event.'
                                : selectedEvent.isReadOnly
                                  ? 'View the details of this event.'
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
                            :disabled="selectedEvent.isReadOnly"
                            class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 text-lg font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Dinner, Soccer, etc."
                        />
                    </div>

                    <div class="flex items-center gap-4">
                        <Label
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >All Day</Label
                        >
                        <Switch
                            v-model:checked="selectedEvent.allDay"
                            :disabled="selectedEvent.isReadOnly"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <Label
                                class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                >Start Date</Label
                            >
                            <Input
                                type="date"
                                v-model="selectedEvent.startDate"
                                :disabled="selectedEvent.isReadOnly"
                                class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <div
                            class="flex flex-col gap-2"
                            v-if="!selectedEvent.allDay"
                        >
                            <Label
                                class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                >Start Time</Label
                            >
                            <Input
                                type="time"
                                v-model="selectedEvent.startTime"
                                :disabled="selectedEvent.isReadOnly"
                                class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <Label
                                class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                >End Date</Label
                            >
                            <Input
                                type="date"
                                v-model="selectedEvent.endDate"
                                :disabled="selectedEvent.isReadOnly"
                                class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <div
                            class="flex flex-col gap-2"
                            v-if="!selectedEvent.allDay"
                        >
                            <Label
                                class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                >End Time</Label
                            >
                            <Input
                                type="time"
                                v-model="selectedEvent.endTime"
                                :disabled="selectedEvent.isReadOnly"
                                class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label
                            for="description"
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Notes (Optional)</Label
                        >
                        <Input
                            id="description"
                            v-model="selectedEvent.description"
                            :disabled="selectedEvent.isReadOnly"
                            class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Any extra details..."
                        />
                    </div>

                    <div
                        class="flex flex-col gap-2"
                        v-if="modalMode === 'create'"
                    >
                        <Label
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Calendar</Label
                        >
                        <template v-if="writableCalendars.length > 0">
                            <select
                                v-model="selectedEvent.calendar_id"
                                class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold outline-none"
                            >
                                <option
                                    v-for="cal in writableCalendars"
                                    :key="cal.id"
                                    :value="cal.id"
                                >
                                    {{ cal.name }}
                                </option>
                            </select>
                        </template>
                        <template v-else>
                            <div
                                class="rounded-2xl bg-red-500/10 p-4 text-sm font-bold text-red-500"
                            >
                                You don't have any authenticated calendars (like
                                Apple iCloud) connected yet. iCal feeds are
                                read-only.
                            </div>
                        </template>
                    </div>

                    <div
                        v-if="eventError"
                        class="rounded-2xl bg-red-500/10 p-4 text-sm font-bold text-red-500"
                    >
                        {{ eventError }}
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
                        v-if="!selectedEvent.isReadOnly"
                        type="submit"
                        @click="saveEvent"
                        :disabled="
                            isSavingEvent ||
                            !selectedEvent.title ||
                            (modalMode === 'create' &&
                                !selectedEvent.calendar_id)
                        "
                        class="h-14 rounded-2xl px-8 text-xs font-black tracking-widest uppercase shadow-none"
                    >
                        <Loader2
                            v-if="isSavingEvent"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Save Event
                    </Button>
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
                    class="custom-scrollbar max-h-[400px] overflow-y-auto pr-2"
                >
                    <draggable
                        v-model="localCalendars"
                        item-key="id"
                        handle=".drag-handle"
                        :animation="200"
                        class="flex flex-col gap-4"
                        @change="emit('reorder-calendars', localCalendars)"
                    >
                        <template #item="{ element: calendar }">
                            <div
                                class="flex items-center justify-between rounded-[2rem] border border-white/5 bg-white/40 p-6 shadow-sm transition-all dark:bg-white/5"
                            >
                                <div class="flex items-center gap-5">
                                    <GripVertical
                                        class="drag-handle text-muted-foreground h-5 w-5 cursor-grab opacity-50 hover:opacity-100"
                                    />
                                    <div
                                        class="h-4 w-4 rounded-full shadow-lg"
                                        :style="{
                                            backgroundColor: calendar.color,
                                        }"
                                    ></div>
                                    <div>
                                        <h4
                                            class="text-xl font-black tracking-tight"
                                        >
                                            {{ calendar.name }}
                                        </h4>
                                        <p
                                            class="mt-1 flex items-center gap-1 text-[10px] font-bold tracking-widest uppercase italic opacity-40"
                                        >
                                            <template
                                                v-if="
                                                    calendar.provider ===
                                                    'apple'
                                                "
                                            >
                                                <RefreshCw class="h-3 w-3" />
                                                Two-Way Sync (Apple)
                                            </template>
                                            <template v-else>
                                                <CalendarIcon class="h-3 w-3" />
                                                Read-Only Feed (iCal)
                                            </template>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <Switch
                                        :checked="
                                            visibleCalendarIds.includes(
                                                calendar.id,
                                            )
                                        "
                                        @update:checked="
                                            emit('toggle-calendar', calendar.id)
                                        "
                                        class="scale-150"
                                    />
                                    <Button
                                        v-if="calendar.provider === 'apple'"
                                        variant="ghost"
                                        size="icon"
                                        :class="[
                                            'h-10 w-10 rounded-full transition-colors',
                                            defaultCalendarId === calendar.id
                                                ? 'text-yellow-500 hover:bg-yellow-500/10 hover:text-yellow-600'
                                                : 'text-muted-foreground hover:bg-black/5 dark:hover:bg-white/10',
                                        ]"
                                        @click="setDefaultCalendar(calendar.id)"
                                        :disabled="isSettingDefault"
                                    >
                                        <Loader2
                                            v-if="
                                                isSettingDefault &&
                                                defaultCalendarId !==
                                                    calendar.id
                                            "
                                            class="h-4 w-4 animate-spin"
                                        />
                                        <Star
                                            v-else
                                            class="h-4 w-4"
                                            :class="{
                                                'fill-current':
                                                    defaultCalendarId ===
                                                    calendar.id,
                                            }"
                                        />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-10 w-10 rounded-full hover:bg-black/5 dark:hover:bg-white/10"
                                        @click="openEditModal(calendar)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-10 w-10 rounded-full text-red-500 hover:bg-red-500/10 hover:text-red-600"
                                        @click="deleteCalendar(calendar.id)"
                                    >
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>

                <div class="mt-6 flex gap-4">
                    <Button
                        variant="outline"
                        class="hover:bg-primary/5 h-16 flex-1 rounded-2xl border-2 border-dashed text-sm font-black tracking-widest uppercase"
                        @click="openImportModal"
                    >
                        <Plus class="mr-2 h-5 w-5" />
                        Add Calendar
                    </Button>
                    <Button
                        class="h-16 flex-1 rounded-2xl text-sm font-black tracking-widest uppercase shadow-none"
                        @click="isFilterDialogOpen = false"
                        >Done</Button
                    >
                </div>
            </DialogContent>
        </Dialog>

        <!-- Import Calendar Modal -->
        <Dialog v-model:open="isImportCalendarModalOpen">
            <DialogContent
                class="rounded-3xl border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[425px] dark:bg-black/95"
            >
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">{{
                        importModalMode === 'create'
                            ? 'Add Web Calendar'
                            : 'Edit Calendar'
                    }}</DialogTitle>
                    <DialogDescription
                        class="text-[10px] font-bold tracking-widest uppercase italic opacity-60"
                    >
                        {{
                            importModalMode === 'create'
                                ? 'Import a public iCal (.ics) or WebCal feed.'
                                : 'Update the settings for this calendar.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-6 py-6">
                    <div
                        class="flex flex-col gap-4"
                        v-if="importModalMode === 'create'"
                    >
                        <Label
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Integration Type</Label
                        >
                        <div class="grid grid-cols-2 gap-4">
                            <button
                                @click.prevent="importForm.provider = 'ical'"
                                :class="[
                                    'flex flex-col items-center justify-center gap-3 rounded-2xl border-2 p-4 text-center transition-all',
                                    importForm.provider === 'ical'
                                        ? 'border-primary bg-primary/10 scale-[1.02]'
                                        : 'border-primary/5 bg-primary/5 opacity-60 hover:scale-[1.01] hover:opacity-100',
                                ]"
                            >
                                <div class="bg-primary/20 rounded-full p-3">
                                    <CalendarIcon
                                        class="text-primary h-6 w-6"
                                    />
                                </div>
                                <div>
                                    <h5 class="font-bold tracking-tight">
                                        Read-Only Feed
                                    </h5>
                                    <p
                                        class="mt-1 text-[10px] font-bold tracking-widest uppercase opacity-60"
                                    >
                                        iCal / WebCal URL
                                    </p>
                                </div>
                            </button>
                            <button
                                @click.prevent="importForm.provider = 'apple'"
                                :class="[
                                    'flex flex-col items-center justify-center gap-3 rounded-2xl border-2 p-4 text-center transition-all',
                                    importForm.provider === 'apple'
                                        ? 'border-primary bg-primary/10 scale-[1.02]'
                                        : 'border-primary/5 bg-primary/5 opacity-60 hover:scale-[1.01] hover:opacity-100',
                                ]"
                            >
                                <div class="bg-primary/20 rounded-full p-3">
                                    <RefreshCw class="text-primary h-6 w-6" />
                                </div>
                                <div>
                                    <h5 class="font-bold tracking-tight">
                                        Two-Way Sync
                                    </h5>
                                    <p
                                        class="mt-1 text-[10px] font-bold tracking-widest uppercase opacity-60"
                                    >
                                        Apple iCloud
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div
                        class="flex flex-col gap-2"
                        v-if="
                            importForm.provider === 'ical' ||
                            availableAppleCalendars.length > 0 ||
                            importModalMode === 'edit'
                        "
                    >
                        <Label
                            for="importName"
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Calendar Name</Label
                        >
                        <Input
                            id="importName"
                            v-model="importForm.name"
                            class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 text-lg font-bold"
                            placeholder="e.g. Work, Sports"
                        />
                    </div>

                    <div
                        v-if="importForm.provider === 'ical'"
                        class="flex flex-col gap-2"
                    >
                        <Label
                            for="importUrl"
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Calendar URL (iCal/Webcal)</Label
                        >
                        <Input
                            id="importUrl"
                            v-model="importForm.url"
                            class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-mono text-xs"
                            placeholder="https://..."
                        />
                    </div>

                    <div
                        v-if="importForm.provider === 'apple'"
                        class="flex flex-col gap-4"
                    >
                        <template
                            v-if="
                                availableAppleCalendars.length === 0 &&
                                importModalMode === 'create'
                            "
                        >
                            <div class="flex flex-col gap-2">
                                <Label
                                    for="importEmail"
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >Apple ID</Label
                                >
                                <Input
                                    id="importEmail"
                                    type="email"
                                    v-model="importForm.email"
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 text-lg font-bold"
                                    placeholder="name@icloud.com"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label
                                    for="importPassword"
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >App-Specific Password</Label
                                >
                                <Input
                                    id="importPassword"
                                    type="password"
                                    v-model="importForm.password"
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-mono text-lg font-bold tracking-widest"
                                    placeholder="abcd-efgh-ijkl-mnop"
                                />
                                <p
                                    class="text-muted-foreground px-1 text-[10px] font-bold"
                                >
                                    You must use an App-Specific Password, not
                                    your standard iCloud password.
                                    <a
                                        href="https://support.apple.com/en-us/102654"
                                        target="_blank"
                                        class="text-blue-500 underline"
                                        >Learn how to generate one</a
                                    >.
                                </p>
                            </div>
                            <Button
                                @click.prevent="fetchAppleCalendars"
                                :disabled="
                                    isFetchingAppleCalendars ||
                                    !importForm.email ||
                                    !importForm.password
                                "
                                class="h-14 rounded-2xl text-xs font-black tracking-widest uppercase"
                            >
                                {{
                                    isFetchingAppleCalendars
                                        ? 'Connecting...'
                                        : 'Fetch Calendars'
                                }}
                            </Button>
                        </template>

                        <template
                            v-else-if="availableAppleCalendars.length > 0"
                        >
                            <div class="flex flex-col gap-2">
                                <Label
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >Select iCloud Calendar</Label
                                >
                                <select
                                    v-model="importForm.apple_calendar_path"
                                    @change="
                                        () => {
                                            const cal =
                                                availableAppleCalendars.find(
                                                    (c) =>
                                                        c.path ===
                                                        importForm.apple_calendar_path,
                                                )
                                            if (cal) importForm.name = cal.name
                                        }
                                    "
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-bold outline-none"
                                >
                                    <option
                                        v-for="cal in availableAppleCalendars"
                                        :key="cal.path"
                                        :value="cal.path"
                                    >
                                        {{ cal.name }}
                                    </option>
                                </select>
                            </div>
                        </template>

                        <template v-else-if="importModalMode === 'edit'">
                            <div class="flex flex-col gap-2">
                                <Label
                                    for="importEmail"
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >Apple ID</Label
                                >
                                <Input
                                    id="importEmail"
                                    type="email"
                                    v-model="importForm.email"
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 text-lg font-bold"
                                    placeholder="name@icloud.com"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label
                                    for="importPassword"
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >App-Specific Password (leave blank to keep
                                    current)</Label
                                >
                                <Input
                                    id="importPassword"
                                    type="password"
                                    v-model="importForm.password"
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-mono text-lg font-bold tracking-widest"
                                    placeholder="abcd-efgh-ijkl-mnop"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label
                                    class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                                    >Calendar Path</Label
                                >
                                <Input
                                    v-model="importForm.apple_calendar_path"
                                    class="border-primary/5 bg-primary/5 h-14 rounded-2xl border-2 px-6 font-mono text-sm opacity-50"
                                    readonly
                                />
                            </div>
                        </template>
                    </div>

                    <div
                        class="flex flex-col gap-2"
                        v-if="
                            importForm.provider === 'ical' ||
                            availableAppleCalendars.length > 0 ||
                            importModalMode === 'edit'
                        "
                    >
                        <Label
                            class="px-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >Color</Label
                        >
                        <div class="flex items-center gap-4">
                            <input
                                ref="colorInputRef"
                                type="color"
                                v-model="importForm.color"
                                @click.stop="openColorPicker"
                                class="h-14 w-20 cursor-pointer rounded-2xl border-none bg-transparent p-0"
                            />
                            <Input
                                v-model="importForm.color"
                                class="border-primary/5 bg-primary/5 h-14 w-32 rounded-2xl border-2 px-4 font-mono text-lg font-bold"
                                placeholder="#000000"
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="importForm.color = getRandomColor()"
                                class="h-14 w-14 rounded-2xl hover:bg-black/5 dark:hover:bg-white/10"
                                title="Randomize Color"
                            >
                                <RefreshCw class="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="importError"
                        class="rounded-2xl bg-red-500/10 p-4 text-sm font-bold text-red-500"
                    >
                        {{ importError }}
                    </div>
                </div>

                <DialogFooter class="gap-3">
                    <Button
                        variant="ghost"
                        @click="isImportCalendarModalOpen = false"
                        :disabled="isImporting"
                        class="h-14 rounded-2xl text-xs font-black tracking-widest uppercase opacity-40 hover:opacity-100"
                        >Cancel</Button
                    >
                    <Button
                        @click="submitImportCalendar"
                        :disabled="
                            isImporting ||
                            (importForm.provider === 'apple' &&
                                availableAppleCalendars.length === 0 &&
                                importModalMode === 'create') ||
                            !importForm.name ||
                            (importForm.provider === 'ical' &&
                                !importForm.url) ||
                            (importForm.provider === 'apple' &&
                                (!importForm.email ||
                                    (!importForm.password &&
                                        importModalMode === 'create') ||
                                    !importForm.apple_calendar_path))
                        "
                        v-if="
                            importForm.provider === 'ical' ||
                            availableAppleCalendars.length > 0 ||
                            importModalMode === 'edit'
                        "
                        class="h-14 rounded-2xl px-8 text-xs font-black tracking-widest uppercase shadow-none"
                    >
                        <Loader2
                            v-if="isImporting"
                            class="mr-2 h-5 w-5 animate-spin"
                        />
                        {{ isImporting ? 'Saving...' : 'Save Calendar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* Adjust FullCalendar for the new embedded layout */
.fc {
    --fc-border-color: rgba(0, 0, 0, 0.1);
    --fc-today-bg-color: rgba(59, 130, 246, 0.05);
    font-family: inherit !important;
}
.dark .fc {
    --fc-border-color: rgba(255, 255, 255, 0.1);
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
