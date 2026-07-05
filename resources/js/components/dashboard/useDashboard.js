import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

export function useDashboard() {
    // UI State
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
    
    // Workspaces
    const workspaces = ref([])

    const getAppName = (id) => {
        const names = { family: 'Calendar', weather: 'Weather', recipes: 'Recipes', shopping: 'Shopping', chores: 'Chores', aura: 'Aura' }
        return names[id] || id
    }

    const loadFilters = () => {
        const savedWorkspaces = localStorage.getItem('dashboard_workspaces')
        if (savedWorkspaces) {
            try {
                const parsed = JSON.parse(savedWorkspaces)
                // Migration: if they have old pinned tabs format (array of strings)
                if (parsed.length > 0 && typeof parsed[0] === 'string') {
                    workspaces.value = parsed.map((appId, index) => ({
                        id: 'ws_' + index + '_' + Math.random().toString(36).substring(2, 9),
                        name: getAppName(appId),
                        layout: 'full',
                        apps: [appId]
                    }))
                    saveFilters()
                } else {
                    workspaces.value = parsed
                }
            } catch (e) {
                console.error(e)
            }
        } else {
            // Default Workspaces
            workspaces.value = [
                { id: 'ws_family', name: 'Calendar', layout: 'full', apps: ['family'] },
                { id: 'ws_weather', name: 'Weather', layout: 'full', apps: ['weather'] },
                { id: 'ws_recipes', name: 'Recipes', layout: 'full', apps: ['recipes'] },
                { id: 'ws_shopping', name: 'Shopping', layout: 'full', apps: ['shopping'] },
                { id: 'ws_chores', name: 'Chores', layout: 'full', apps: ['chores'] },
            ]
            saveFilters()
        }

        const saved = localStorage.getItem('dashboard_filters_map')
        if (saved) filtersByProfile.value = JSON.parse(saved)

        const savedTimezone = localStorage.getItem('dashboard_timezone')
        if (savedTimezone) localTimezone.value = savedTimezone

        const savedOrder = localStorage.getItem('dashboard_calendar_order')
        if (savedOrder) calendarOrder.value = JSON.parse(savedOrder)
    }

    const saveFilters = () => {
        localStorage.setItem(
            'dashboard_filters_map',
            JSON.stringify(filtersByProfile.value),
        )
        localStorage.setItem('dashboard_timezone', localTimezone.value)
        localStorage.setItem(
            'dashboard_calendar_order',
            JSON.stringify(calendarOrder.value),
        )
        localStorage.setItem(
            'dashboard_workspaces',
            JSON.stringify(workspaces.value)
        )
    }

    const createWorkspace = (appId) => {
        const newWs = {
            id: 'ws_' + Math.random().toString(36).substring(2, 9),
            name: getAppName(appId),
            layout: 'full',
            apps: [appId]
        }
        workspaces.value.push(newWs)
        saveFilters()
        return newWs
    }

    const removeWorkspace = (workspaceId) => {
        workspaces.value = workspaces.value.filter(ws => ws.id !== workspaceId)
        saveFilters()
    }

    const updateWorkspace = (workspaceId, updates) => {
        const ws = workspaces.value.find(w => w.id === workspaceId)
        if (ws) {
            Object.assign(ws, updates)
            saveFilters()
        }
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

            let fetchedCalendars = response.data.calendars
            if (calendarOrder.value.length > 0) {
                fetchedCalendars.sort((a, b) => {
                    const idxA = calendarOrder.value.indexOf(a.id)
                    const idxB = calendarOrder.value.indexOf(b.id)
                    if (idxA === -1 && idxB === -1) return 0
                    if (idxA === -1) return 1
                    if (idxB === -1) return -1
                    return idxA - idxB
                })
            } else {
                calendarOrder.value = fetchedCalendars.map((c) => c.id)
                saveFilters()
            }
            availableCalendars.value = fetchedCalendars

            defaultCalendarId.value = response.data.default_calendar_id || null

            const dbProfiles = response.data.profiles
            let profileWasNull = false
            if (dbProfiles) {
                dbProfiles.forEach((p) => {
                    if (p.visible_calendars !== null) {
                        filtersByProfile.value[p.name] = p.visible_calendars.map(Number)
                    } else if (p.name === activeProfile.value) {
                        profileWasNull = true
                    }
                })
            }

            // Automatically enable newly added calendars
            const knownIds = filtersByProfile.value[activeProfile.value] || []
            const fetchedIds = availableCalendars.value.map((c) => Number(c.id))
            
            if (profileWasNull && knownIds.length === 0) {
                // First time ever loading this profile, enable all
                filtersByProfile.value[activeProfile.value] = fetchedIds
            } else {
                // If the user already configured this profile, only auto-enable brand NEW calendars
                // Wait, if a new calendar was added globally, it shouldn't necessarily auto-enable for everyone.
                // Let's just leave their configured visible list alone!
                // If they want to see a new calendar, they can toggle it on.
                // So we do nothing here!
            }
            saveFilters()

            if (!start && !end) {
                scheduleEvents.value = response.data.events
            } else if (
                scheduleEvents.value.length === 0 ||
                fetchedIds.filter((id) => !knownIds.includes(id)).length > 0
            ) {
                // Fetch schedule events separately if we just added a calendar
                axios
                    .get('/api/events', {
                        params: { profile: activeProfile.value },
                    })
                    .then((res) => {
                        scheduleEvents.value = res.data.events
                    })
            }
        } catch (error) {
            console.error('Failed to fetch events:', error)
        } finally {
            isLoading.value = false
        }
    }

    const toggleCalendar = async (id) => {
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

        // Sync with backend
        try {
            await axios.post(`/api/profiles/${profile}/visible-calendars`, {
                visible_calendars: currentIds
            })
        } catch (e) {
            console.error('Failed to update visible calendars on backend', e)
        }
    }

    const reorderCalendars = (newOrder) => {
        availableCalendars.value = newOrder
        calendarOrder.value = newOrder.map((c) => c.id)
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

    onMounted(() => {
        loadFilters()
        fetchEvents()
    })

    watch(activeProfile, () => {
        fetchEvents()
    })

    return {
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
        defaultCalendarId,
        workspaces,
        createWorkspace,
        removeWorkspace,
        updateWorkspace
    }
}
