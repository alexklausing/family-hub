import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

export function useDashboard() {
    // UI State
    const isEditMode = ref(false)
    const isSettingsDialogOpen = ref(false)
    const isSyncModalOpen = ref(false)
    const syncOption = ref('current')

    const activeProfile = ref('Family')

    // Calendars and Filtering
    const availableCalendars = ref([])
    const defaultCalendarId = ref(null)
    const filtersByProfile = ref({})

    // Timezone
    const localTimezone = ref(
        Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/New_York',
    )

    const loadFilters = () => {
        const saved = localStorage.getItem('dashboard_filters_map')
        if (saved) filtersByProfile.value = JSON.parse(saved)

        const savedTimezone = localStorage.getItem('dashboard_timezone')
        if (savedTimezone) localTimezone.value = savedTimezone
    }

    const saveFilters = () => {
        localStorage.setItem(
            'dashboard_filters_map',
            JSON.stringify(filtersByProfile.value),
        )
        localStorage.setItem('dashboard_timezone', localTimezone.value)
    }

    const visibleCalendarIds = computed(() => {
        const ids = filtersByProfile.value[activeProfile.value] || []
        return [...new Set(ids.map((id) => Number(id)))]
    })

    const allEvents = ref([])
    const scheduleEvents = ref([])
    const isLoading = ref(false)
    const isSyncing = ref(false)

    const filteredEvents = computed(() => {
        const visibleIds = visibleCalendarIds.value
        if (availableCalendars.value.length === 0 || visibleIds.length === 0)
            return []
        return allEvents.value.filter((event) =>
            visibleIds.includes(Number(event.calendar_id)),
        )
    })

    const filteredScheduleEvents = computed(() => {
        const visibleIds = visibleCalendarIds.value
        if (availableCalendars.value.length === 0 || visibleIds.length === 0)
            return []
        return scheduleEvents.value.filter((event) =>
            visibleIds.includes(Number(event.calendar_id)),
        )
    })

    const fetchEvents = async (start = null, end = null) => {
        isLoading.value = true
        try {
            const response = await axios.get('/api/events', {
                params: {
                    profile: activeProfile.value,
                    start,
                    end,
                },
            })
            allEvents.value = response.data.events
            availableCalendars.value = response.data.calendars
            defaultCalendarId.value = response.data.default_calendar_id || null

            // Automatically enable newly added calendars
            const knownIds = filtersByProfile.value[activeProfile.value] || []
            const fetchedIds = availableCalendars.value.map((c) => Number(c.id))
            if (knownIds.length === 0) {
                filtersByProfile.value[activeProfile.value] = fetchedIds
            } else {
                const newIds = fetchedIds.filter(id => !knownIds.includes(id))
                if (newIds.length > 0) {
                    filtersByProfile.value[activeProfile.value] = [...knownIds, ...newIds]
                }
            }
            saveFilters()

            if (!start && !end) {
                scheduleEvents.value = response.data.events
            } else if (scheduleEvents.value.length === 0 || fetchedIds.filter(id => !knownIds.includes(id)).length > 0) {
                // Fetch schedule events separately if we just added a calendar
                axios.get('/api/events', { params: { profile: activeProfile.value } })
                    .then(res => {
                        scheduleEvents.value = res.data.events
                    })
            }
        } catch (error) {
            console.error('Failed to fetch events:', error)
        } finally {
            isLoading.value = false
        }
    }

    const toggleCalendar = (id) => {
        const profile = activeProfile.value
        const targetId = Number(id)

        if (!filtersByProfile.value[profile]) {
            filtersByProfile.value[profile] = availableCalendars.value.map(
                (c) => Number(c.id),
            )
        }

        const currentIds = filtersByProfile.value[profile].map((i) => Number(i))
        const index = currentIds.indexOf(targetId)

        if (index > -1) {
            currentIds.splice(index, 1)
        } else {
            currentIds.push(targetId)
        }

        filtersByProfile.value[profile] = currentIds
        saveFilters()
    }

    const handleSync = async () => {
        isSyncing.value = true
        isSyncModalOpen.value = false
        try {
            if (syncOption.value === 'all') {
                await axios.post('/api/sync/all')
            } else {
                await axios.post('/api/sync/calendars')
            }
            await fetchEvents()
        } catch (error) {
            console.error('Sync failed:', error)
        } finally {
            isSyncing.value = false
        }
    }

    const resetLayout = () => {
        isEditMode.value = false
    }

    onMounted(() => {
        loadFilters()
        fetchEvents()
    })

    watch(activeProfile, () => {
        fetchEvents()
    })

    return {
        isEditMode,
        isSettingsDialogOpen,
        isSyncModalOpen,
        syncOption,
        activeProfile,
        availableCalendars,
        filtersByProfile,
        localTimezone,
        saveFilters,
        visibleCalendarIds,
        allEvents,
        scheduleEvents,
        isLoading,
        isSyncing,
        filteredEvents,
        filteredScheduleEvents,
        fetchEvents,
        toggleCalendar,
        handleSync,
        resetLayout,
        defaultCalendarId,
    }
}
