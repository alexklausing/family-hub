import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'

export function useDashboard() {
    // Guards against stale in-flight responses clobbering newer ones.
    let fetchSequence = 0
    // UI State
    const isSettingsDialogOpen = ref(false)
    const isSyncModalOpen = ref(false)
    const syncOption = ref('current')

    const activeProfile = ref('Family')

    // Calendars and Filtering
    const availableCalendars = ref([])
    const defaultCalendarId = ref(null)
    const filtersByProfile = ref({})
    const calendarOrder = ref([])

    // Timezone
    const localTimezone = ref(
        Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/New_York',
    )
    const timeOffset = ref(0)
    
    // Workspaces
    const workspaces = ref([])

    // Monitor Settings
    const monitorSettings = ref({
        enabled: true,
        off: '22:00',
        on: '07:00'
    })

    // Home Address (shared with Commute app). Pre-seeded with the family home
    // so the Commute app works out of the box.
    const DEFAULT_HOME_ADDRESS = {
        address: '1271 Evergreen Park Circle, Lakeland, FL 33813',
        lat: 27.9487506,
        lon: -81.9417946,
    }
    const homeAddress = ref({ ...DEFAULT_HOME_ADDRESS })

    const getAppName = (id) => {
        const names = { family: 'Calendar', weather: 'Weather', recipes: 'Recipes', shopping: 'Shopping', chores: 'Chores', aura: 'Aura', 'lunch-menu': 'School Lunch', commute: 'Commute' }
        return names[id] || id
    }

    const unusedApps = ref([])

    const loadFilters = () => {
        // Load the stored home address up front so later saveFilters() calls
        // (workspace migration, unused apps) can't overwrite it with the seed.
        const savedHome = localStorage.getItem('dashboard_home_address')
        if (savedHome) {
            try {
                const parsed = JSON.parse(savedHome)
                // Ignore empty persisted values so a never-configured install
                // keeps the auto-seeded default home address.
                if (parsed.address || parsed.lat) {
                    homeAddress.value = { address: '', lat: null, lon: null, ...parsed }
                }
            } catch (e) {
                // Corrupt data — keep default
            }
        }

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
                // Commute is a standalone app (launched from Other Apps), never a tab.
                workspaces.value = workspaces.value
                    .map((ws) => ({ ...ws, apps: (ws.apps || []).filter((id) => id !== 'commute' && id !== 'commute-widget') }))
                    .filter((ws) => ws.apps.length > 0)
                if (workspaces.value.length !== parsed.length) saveFilters()
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
        
        const savedOffset = localStorage.getItem('dashboard_time_offset')
        if (savedOffset) timeOffset.value = parseInt(savedOffset) || 0

        const savedOrder = localStorage.getItem('dashboard_calendar_order')
        if (savedOrder) calendarOrder.value = JSON.parse(savedOrder)

        const savedUnused = localStorage.getItem('dashboard_unused_apps')
        if (savedUnused) {
            unusedApps.value = JSON.parse(savedUnused)
        } else {
            unusedApps.value = []
            saveFilters()
        }

        const savedMonitor = localStorage.getItem('dashboard_monitor_settings')
        if (savedMonitor) {
            monitorSettings.value = JSON.parse(savedMonitor)
        }
    }

    const saveFilters = () => {
        localStorage.setItem(
            'dashboard_filters_map',
            JSON.stringify(filtersByProfile.value),
        )
        localStorage.setItem('dashboard_timezone', localTimezone.value)
        localStorage.setItem('dashboard_time_offset', timeOffset.value.toString())
        localStorage.setItem(
            'dashboard_calendar_order',
            JSON.stringify(calendarOrder.value),
        )
        localStorage.setItem(
            'dashboard_workspaces',
            JSON.stringify(workspaces.value)
        )
        localStorage.setItem(
            'dashboard_unused_apps',
            JSON.stringify(unusedApps.value)
        )
        localStorage.setItem(
            'dashboard_monitor_settings',
            JSON.stringify(monitorSettings.value)
        )
        localStorage.setItem(
            'dashboard_home_address',
            JSON.stringify(homeAddress.value)
        )
    }

    const createWorkspace = (appId) => {
        // Commute lives only in the Others app library — never as its own tab.
        if (appId === 'commute') return null
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

    const reorderWorkspaces = (fromIdx, toIdx) => {
        const arr = [...workspaces.value]
        const item = arr.splice(fromIdx, 1)[0]
        arr.splice(toIdx, 0, item)
        workspaces.value = arr
        saveFilters()
    }

    const resetWorkspaces = () => {
        workspaces.value = [
            { id: 'ws_family', name: 'Calendar', layout: 'full', apps: ['family'] },
            { id: 'ws_weather', name: 'Weather', layout: 'full', apps: ['weather'] },
            { id: 'ws_recipes', name: 'Recipes', layout: 'full', apps: ['recipes'] },
            { id: 'ws_shopping', name: 'Shopping', layout: 'full', apps: ['shopping'] },
            { id: 'ws_chores', name: 'Chores', layout: 'full', apps: ['chores'] },
        ]
        unusedApps.value = []
        saveFilters()
    }

    const toggleAppActive = (appId) => {
        const idx = unusedApps.value.indexOf(appId)
        if (idx > -1) {
            unusedApps.value.splice(idx, 1)
        } else {
            unusedApps.value.push(appId)
        }
        saveFilters()
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

    const mergeEvents = (existing, incoming) => {
        // Union windowed responses into what's already loaded so browsing a
        // narrow range never blanks calendars that had events elsewhere.
        // Incoming wins for duplicate ids so updated events replace stale ones.
        const merged = new Map()
        for (const event of incoming) {
            merged.set(event.id, event)
        }
        for (const event of existing) {
            if (!merged.has(event.id)) {
                merged.set(event.id, event)
            }
        }
        return [...merged.values()]
    }

    const fetchEvents = async (start = null, end = null) => {
        const seq = ++fetchSequence
        isLoading.value = true
        try {
            const response = await axios.get('/api/events', {
                params: {
                    profile: activeProfile.value,
                    start,
                    end,
                },
            })

            // A newer request superseded this one; discard the stale response.
            if (seq !== fetchSequence) return

            allEvents.value =
                !start && !end
                    ? response.data.events
                    : mergeEvents(allEvents.value, response.data.events)

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
                        const local = filtersByProfile.value[p.name]
                        // Seed a profile's visibility from the backend only the
                        // first time we see it locally. Afterwards local wins,
                        // so a slow response can't clobber a user's toggle.
                        if (local === undefined) {
                            filtersByProfile.value[p.name] =
                                p.visible_calendars.map(Number)
                        }
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
                        if (seq === fetchSequence) {
                            scheduleEvents.value = res.data.events
                        }
                    })
            }
        } catch (error) {
            console.error('Failed to fetch events:', error)
        } finally {
            if (seq === fetchSequence) {
                isLoading.value = false
            }
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
        } catch (error) {
            console.error('Sync failed:', error)
        }
        // Always refresh from the cache afterwards, even if the sync call
        // itself failed, so the grid reflects the latest synced data.
        await fetchEvents()
        isSyncing.value = false
    }

    let resumeTimer = null
    let lastRefreshAt = 0

    const refreshEventsIfStale = () => {
        // Debounce bursts (initial visibilitychange + focus + pageshow can
        // all fire within the same second) and space hard refreshes out.
        const now = Date.now()
        if (now - lastRefreshAt < 3000) return
        lastRefreshAt = now
        if (resumeTimer) clearTimeout(resumeTimer)
        resumeTimer = setTimeout(() => fetchEvents(), 500)
    }

    const handleVisibilityChange = () => {
        // Returning to a hidden tab / waking the kiosk: pull fresh data
        // instead of leaving the grid stuck in a stale or partial state.
        if (!document.hidden) refreshEventsIfStale()
    }

    const handleFocus = () => {
        if (!document.hidden) refreshEventsIfStale()
    }

    const handlePageShow = (event) => {
        // ~persisted: page restored from the bfcache without a new load.
        if (event.persisted) refreshEventsIfStale()
    }

    onMounted(() => {
        loadFilters()
        lastRefreshAt = Date.now()
        fetchEvents()

        document.addEventListener('visibilitychange', handleVisibilityChange)
        window.addEventListener('focus', handleFocus)
        window.addEventListener('pageshow', handlePageShow)
    })

    onUnmounted(() => {
        if (resumeTimer) clearTimeout(resumeTimer)
        document.removeEventListener(
            'visibilitychange',
            handleVisibilityChange,
        )
        window.removeEventListener('focus', handleFocus)
        window.removeEventListener('pageshow', handlePageShow)
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
        timeOffset,
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
        updateWorkspace,
        reorderWorkspaces,
        resetWorkspaces,
        unusedApps,
        toggleAppActive,
        monitorSettings,
        homeAddress
    }
}
