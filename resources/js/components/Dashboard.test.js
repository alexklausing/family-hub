import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import Dashboard from './Dashboard.vue'
import axios from 'axios'
import { useSleepException } from '@/composables/useSleepException'

vi.mock('axios')

// Shared handle lets tests drive monitorSettings through the mocked composable.
const dashState = vi.hoisted(() => ({ monitorSettings: null }))

vi.mock('./dashboard/useDashboard', async () => {
    const { ref, computed } = await import('vue')
    dashState.monitorSettings = ref({
        enabled: true,
        off: '60:00',
        on: '60:01',
        idleTimeout: 0,
    })

    return {
        useDashboard: () => ({
            isSettingsDialogOpen: ref(false),
            isSyncModalOpen: ref(false),
            syncOption: ref('current'),
            activeProfile: ref('Family'),
            availableCalendars: ref([]),
            visibleCalendarIds: computed(() => []),
            isSyncing: ref(false),
            filteredEvents: ref([]),
            filteredScheduleEvents: ref([]),
            fetchEvents: vi.fn(),
            toggleCalendar: vi.fn(),
            handleSync: vi.fn(),
            localTimezone: ref('UTC'),
            timeOffset: ref(0),
            saveFilters: vi.fn(),
            defaultCalendarId: ref(null),
            reorderCalendars: vi.fn(),
            workspaces: ref([]),
            createWorkspace: vi.fn(),
            removeWorkspace: vi.fn(),
            updateWorkspace: vi.fn(),
            reorderWorkspaces: vi.fn(),
            resetWorkspaces: vi.fn(),
            unusedApps: ref([]),
            toggleAppActive: vi.fn(),
            monitorSettings: dashState.monitorSettings,
        }),
    }
})

vi.mock('../composables/useWeather', async () => {
    const { ref } = await import('vue')
    return {
        useWeather: () => ({
            alerts: ref([]),
            fetchWeather: vi.fn(),
            weatherData: ref(null),
        }),
    }
})

const createStorageMock = () => {
    const store = new Map()
    return {
        getItem: vi.fn((k) => (store.has(k) ? store.get(k) : null)),
        setItem: vi.fn((k, v) => store.set(k, String(v))),
        removeItem: vi.fn((k) => store.delete(k)),
        clear: vi.fn(() => store.clear()),
        key: vi.fn((i) => [...store.keys()][i] ?? null),
        get length() {
            return store.size
        },
    }
}

const mountOptions = {
    global: {
        stubs: {
            Tabs: true,
            TabsContent: true,
            Button: true,
            Dialog: true,
            DialogContent: true,
            DialogHeader: true,
            DialogTitle: true,
            DialogDescription: true,
            WorkspaceView: true,
            OtherTab: true,
            AuraTab: true,
            CelebrationTab: true,
            DashboardHeader: true,
            SettingsDialog: true,
            SyncDialog: true,
            VirtualKeyboard: true,
            ConfirmDialog: true,
        },
    },
}

describe('Dashboard auto-sleep settings', () => {
    let store
    let wrapper

    const setIdleTimeout = async (minutes) => {
        dashState.monitorSettings.value.idleTimeout = minutes
        await nextTick()
    }

    beforeEach(() => {
        vi.useFakeTimers()
        document.body.innerHTML = ''
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })
        axios.get.mockReset()
        axios.get.mockResolvedValue({ data: { version: '1' } })
        axios.post.mockReset()
        axios.post.mockResolvedValue({ data: {} })

        store = useSleepException()
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }

        dashState.monitorSettings.value = {
            enabled: true,
            off: '60:00',
            on: '60:01',
            idleTimeout: 0,
        }
        wrapper = mount(Dashboard, mountOptions)
    })

    afterEach(() => {
        if (wrapper) wrapper.unmount()
        vi.useRealTimers()
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }
    })

    it('sleeps after the user-configured inactivity duration', async () => {
        await setIdleTimeout(5)

        await vi.advanceTimersByTimeAsync(4 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)

        await vi.advanceTimersByTimeAsync(1 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)
        expect(axios.post).toHaveBeenCalledWith('/api/monitor/state', {
            state: 'sleep',
        })
    })

    it('never sleeps when the inactivity timer is set to Never (0)', async () => {
        await vi.advanceTimersByTimeAsync(10 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)
    })

    it('does not sleep while auto-sleep is disabled', async () => {
        await setIdleTimeout(5)
        dashState.monitorSettings.value.enabled = false
        await nextTick()

        await vi.advanceTimersByTimeAsync(10 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)

        dashState.monitorSettings.value.enabled = true
        await nextTick()
        await vi.advanceTimersByTimeAsync(5 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)
    })

    it('resets the inactivity countdown on user interaction', async () => {
        await setIdleTimeout(5)

        await vi.advanceTimersByTimeAsync(3 * 60 * 1000)
        window.dispatchEvent(new Event('mousemove'))
        await vi.advanceTimersByTimeAsync(3 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)

        await vi.advanceTimersByTimeAsync(2 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)
    })

    it('keeps the hub awake while a sleep exception is active, then resumes after it clears', async () => {
        store.enableException('test-feature')

        await setIdleTimeout(5)
        await vi.advanceTimersByTimeAsync(10 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)

        store.disableException('test-feature')
        await nextTick()
        await vi.advanceTimersByTimeAsync(5 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)
    })

    it('wakes the hub when a sleep exception activates while asleep', async () => {
        await setIdleTimeout(5)
        await vi.advanceTimersByTimeAsync(5 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)

        store.enableException('test-feature')
        await nextTick()
        expect(wrapper.vm.isSleeping).toBe(false)
        expect(axios.post).toHaveBeenCalledWith('/api/monitor/state', {
            state: 'wake',
        })
    })

    it('applies a newly chosen duration immediately', async () => {
        // Start with 5 minutes, then switch to 15 in the settings
        await setIdleTimeout(5)
        await setIdleTimeout(15)

        await vi.advanceTimersByTimeAsync(10 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(false)

        await vi.advanceTimersByTimeAsync(5 * 60 * 1000)
        expect(wrapper.vm.isSleeping).toBe(true)
    })
})
