<script setup>
import { ref, computed } from 'vue'
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
import { Button } from '@/components/ui/button'

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['range-changed'])

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
const calendarOptions = computed(() => ({
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
        }
    },
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,twoWeekView,timeGridWeek,timeGridDay',
    },
    events: props.events.map(event => ({
        id: event.id,
        title: event.title,
        start: event.start,
        end: event.end,
        allDay: event.all_day,
        backgroundColor: event.calendar?.color || '#3b82f6',
        borderColor: event.calendar?.color || '#3b82f6',
        extendedProps: {
            provider: event.calendar?.provider,
            calendarName: event.calendar?.name
        }
    })),

    // Touch / Interaction Settings
    dayMaxEvents: false,
    selectable: true,
    selectMirror: true,
    editable: false, // Disables drag-and-drop & resizing as requested

    // Callbacks
    select: handleDateSelect,
    eventClick: handleEventClick,
    datesSet: (info) => {
        emit('range-changed', { start: info.startStr, end: info.endStr })
    }
}))

function handleDateSelect(selectInfo) {
    // Prevent selection if we want to enforce pure click-to-add
    // selectInfo.view.calendar.unselect()

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
    // Placeholder for saving logic to the backend via API
    console.log('Saving event:', selectedEvent.value)
    isModalOpen.value = false
}
</script>

<template>
    <div class="calendar-container h-full w-full">
        <FullCalendar :options="calendarOptions" class="h-full" />

        <!-- Event Management Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{
                        modalMode === 'create' ? 'Add New Event' : 'Edit Event'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            modalMode === 'create'
                                ? 'Enter the details for your new event.'
                                : 'Modify the details of this event.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="title" class="text-right">Title</Label>
                        <Input
                            id="title"
                            v-model="selectedEvent.title"
                            class="col-span-3"
                        />
                    </div>
                    <!-- More fields for date/time will go here -->
                    <div class="text-muted-foreground mt-4 text-center text-sm">
                        <p>Start: {{ selectedEvent.start }}</p>
                        <p>End: {{ selectedEvent.end }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isModalOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" @click="saveEvent">Save Event</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* Adjust FullCalendar to look good on the 24" touch display */
.fc-theme-standard .fc-scrollgrid {
    border-radius: var(--radius);
    overflow: hidden;
}
.fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 600 !important;
}
.fc-button {
    padding: 0.5rem 1rem !important;
    font-size: 1.125rem !important; /* Larger touch targets */
}
.fc-event {
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
}
</style>
