import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import { useDashboard } from './useDashboard'

vi.mock('axios')

let api
let wrapper

const Harness = defineComponent({
    setup() {
        api = useDashboard()
        return () => h('div')
    },
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

const SEED_HOME = {
    address: '1271 Evergreen Park Circle, Lakeland, FL 33813',
    lat: 27.9487506,
    lon: -81.9417946,
}

const mountApi = async () => {
    wrapper = mount(Harness)
    await flushPromises()
}

describe('useDashboard', () => {
    beforeEach(() => {
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })
        axios.get.mockReset()
        axios.get.mockResolvedValue({
            data: {
                events: [],
                calendars: [],
                default_calendar_id: null,
                profiles: [],
            },
        })
    })

    afterEach(() => {
        wrapper?.unmount()
    })

    it('auto-seeds the home address when nothing is configured', async () => {
        await mountApi()

        expect(api.homeAddress.value).toEqual(SEED_HOME)
    })

    it('keeps a previously configured home address over the seed', async () => {
        localStorage.setItem(
            'dashboard_home_address',
            JSON.stringify({
                address: '123 Other St, Lakeland, FL 33803',
                lat: 28.0011,
                lon: -81.9517,
            }),
        )

        await mountApi()

        expect(api.homeAddress.value).toEqual({
            address: '123 Other St, Lakeland, FL 33803',
            lat: 28.0011,
            lon: -81.9517,
        })
    })

    it('falls back to the seed when the saved home address was never set', async () => {
        localStorage.setItem(
            'dashboard_home_address',
            JSON.stringify({ address: '', lat: null, lon: null }),
        )

        await mountApi()

        expect(api.homeAddress.value).toEqual(SEED_HOME)
    })

    it('refuses to pin commute as its own workspace tab', async () => {
        await mountApi()

        const before = api.workspaces.value.length
        expect(api.createWorkspace('commute')).toBeNull()
        expect(api.workspaces.value.length).toBe(before)

        const ws = api.createWorkspace('weather')
        expect(ws.apps).toEqual(['weather'])
        expect(api.workspaces.value).toHaveLength(before + 1)
    })

    it('strips stale commute tabs from saved workspaces on load', async () => {
        localStorage.setItem(
            'dashboard_workspaces',
            JSON.stringify([
                {
                    id: 'ws_family',
                    name: 'Calendar',
                    layout: 'full',
                    apps: ['family'],
                },
                {
                    id: 'ws_commute',
                    name: 'Commute',
                    layout: 'full',
                    apps: ['commute'],
                },
                {
                    id: 'ws_widget',
                    name: 'Commute',
                    layout: 'full',
                    apps: ['commute-widget'],
                },
            ]),
        )

        await mountApi()

        expect(api.workspaces.value.map((w) => w.id)).toEqual(['ws_family'])
    })

    it('enables a newly seeded calendar only for profiles where it is a backend default', async () => {
        localStorage.setItem('dashboard_calendar_order', JSON.stringify([1, 2]))
        localStorage.setItem(
            'dashboard_filters_map',
            JSON.stringify({ Family: [1], Alex: [2] }),
        )
        axios.get.mockResolvedValue({
            data: {
                events: [],
                calendars: [
                    { id: 1, name: 'Family' },
                    { id: 2, name: 'Work' },
                    {
                        id: 3,
                        name: 'Paprika Menu Plan',
                        external_id: 'paprika_menu_plan',
                    },
                ],
                default_calendar_id: null,
                profiles: [
                    { name: 'Family', visible_calendars: [1, 3] },
                    { name: 'Alex', visible_calendars: [2] },
                ],
            },
        })

        await mountApi()

        expect(api.filtersByProfile.value).toEqual({
            Family: [1, 3],
            Alex: [2],
        })
        expect(
            JSON.parse(localStorage.getItem('dashboard_calendar_order')),
        ).toEqual([1, 2, 3])
    })
})
