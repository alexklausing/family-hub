import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import CommuteTab from './CommuteTab.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

const chainable = () => {
    const target = {}
    const handler = {
        get: (obj, prop) => {
            if (prop === 'then') return undefined
            return (...args) => {
                obj._calls = obj._calls || []
                obj._calls.push(prop)
                return result
            }
        },
    }
    const result = new Proxy(target, handler)
    return result
}

const leafletMocks = vi.hoisted(() => {
    const createFakeMap = () => ({
        view: null,
        handlers: {},
        setView(latlng, zoom) {
            this.view = { latlng, zoom }
            return this
        },
        on(event, handler) {
            this.handlers[event] = this.handlers[event] || []
            this.handlers[event].push(handler)
            return this
        },
        getCenter() {
            const v = this.view
            return v && Array.isArray(v.latlng)
                ? { lat: v.latlng[0], lng: v.latlng[1] }
                : { lat: 0, lng: 0 }
        },
        getZoom() {
            return this.view ? this.view.zoom : 0
        },
        fitBounds: vi.fn(function (bounds) {
            this.view = { latlng: bounds, zoom: null }
            return this
        }),
        remove() {
            return this
        },
        addLayer() {
            return this
        },
        removeLayer() {
            return this
        },
    })
    return { createFakeMap }
})

const polylineMock = vi.fn(() => chainable())
const markerMock = vi.fn(() => chainable())
const tileLayerMock = vi.fn(() => chainable())

vi.mock('leaflet', () => ({
    default: {
        map: vi.fn(() => leafletMocks.createFakeMap()),
        tileLayer: (...args) => tileLayerMock(...args),
        divIcon: vi.fn(() => ({})),
        marker: (...args) => markerMock(...args),
        polyline: (...args) => polylineMock(...args),
        DomEvent: { stopPropagation: vi.fn() },
    },
}))

const home = ref({ address: '1271 Evergreen Park Cir', lat: 28.0631, lon: -81.9485 })

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

const etaResponse = () => [
    {
        destination: {
            id: 1,
            name: 'Lakeland Montessori',
            address: '1124 N. Lake Parker Ave, Lakeland, FL 33805',
            lat: 28.0636,
            lon: -81.9434,
            icon: 'GraduationCap',
        },
        route: {
            travel_time_seconds: 720,
            travel_time_minutes: 12,
            traffic_delay_seconds: 180,
            traffic_delay_minutes: 3,
            no_traffic_time_seconds: 540,
            no_traffic_time_minutes: 9,
            distance_meters: 8047,
            distance_miles: 5,
            polyline: [
                { latitude: 28.0631, longitude: -81.9485 },
                { latitude: 28.0636, longitude: -81.9434 },
            ],
        },
        traffic_sections: [],
        fetched_at: '2026-09-14T12:00:00Z',
    },
]

const STUBS = {
    Dialog: { template: '<div><slot /></div>' },
    DialogContent: { template: '<div><slot /></div>' },
    DialogHeader: { template: '<div><slot /></div>' },
    DialogTitle: { template: '<div><slot /></div>' },
    DialogDescription: { template: '<div><slot /></div>' },
    Button: { template: '<button><slot /></button>' },
    Input: { template: '<input />' },
}

describe('CommuteTab', () => {
    let wrapper

    beforeEach(() => {
        vi.clearAllMocks()
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })
        polylineMock.mockClear()
        markerMock.mockClear()
        tileLayerMock.mockClear()
        axios.get.mockReset()
        axios.post.mockReset()

        axios.get.mockImplementation((url) => {
            if (url === '/api/commute/destinations') {
                return Promise.resolve({
                    data: [
                        {
                            id: 1,
                            name: 'Lakeland Montessori',
                            address: '1124 N. Lake Parker Ave, Lakeland, FL 33805',
                            lat: 28.0636,
                            lon: -81.9434,
                            icon: 'GraduationCap',
                        },
                    ],
                })
            }
            if (url === '/api/commute/etas') {
                return Promise.resolve({ data: etaResponse() })
            }
            return Promise.reject(new Error('unexpected url ' + url))
        })
        axios.post.mockResolvedValue({ data: etaResponse() })
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    const mountTab = async () => {
        wrapper = mount(CommuteTab, {
            global: {
                provide: {
                    homeAddress: home,
                },
                stubs: STUBS,
            },
        })
        await new Promise((r) => setTimeout(r, 0))
        await new Promise((r) => setTimeout(r, 0))
    }

    const fakeMap = () => L.map.mock.results[0].value

    const mapButton = (title) =>
        wrapper.findAll('button').find((b) => b.attributes('title') === title)

    it('renders destination ETA cards from the etas endpoint', async () => {
        await mountTab()

        expect(axios.get).toHaveBeenCalledWith('/api/commute/etas', {
            params: { home_lat: 28.0631, home_lon: -81.9485 },
        })
        expect(wrapper.text()).toContain('Lakeland Montessori')
        expect(wrapper.text()).toContain('12')
        expect(wrapper.text()).toContain('Slight delay')
        expect(wrapper.text()).toContain('normally 9 min · 5 mi')
        expect(wrapper.text()).toContain('Arrive')
    })

    it('shows "Major Delay" for routes with 5+ minutes of congestion', async () => {
        axios.get.mockResolvedValue({
            data: [
                {
                    destination: {
                        id: 1,
                        name: 'Florida Southern College',
                        lat: 28.0327,
                        lon: -81.9502,
                    },
                    route: {
                        travel_time_minutes: 30,
                        traffic_delay_minutes: 8,
                        no_traffic_time_minutes: 22,
                        distance_miles: 8,
                    },
                },
            ],
        })
        await mountTab()

        expect(wrapper.text()).toContain('Major Delay')
        expect(wrapper.text()).not.toContain('Slight delay')
    })

    it('renders no delay chip for on-time routes', async () => {
        axios.get.mockResolvedValue({
            data: [
                {
                    destination: {
                        id: 2,
                        name: 'Home Depot',
                        lat: 28.05,
                        lon: -81.9,
                    },
                    route: {
                        travel_time_minutes: 10,
                        traffic_delay_minutes: 1,
                        no_traffic_time_minutes: 9,
                        distance_miles: 3,
                    },
                },
            ],
        })
        await mountTab()

        expect(wrapper.text()).toContain('Home Depot')
        expect(wrapper.text()).not.toContain('Slight delay')
        expect(wrapper.text()).not.toContain('Major Delay')
    })

    it('creates a route polyline on the map', async () => {
        await mountTab()

        expect(polylineMock).toHaveBeenCalled()
        const args = polylineMock.mock.calls[0][0]
        expect(args).toHaveLength(2)
        expect(polylineMock.mock.calls[0][1]).toEqual(
            expect.objectContaining({ color: '#f59e0b' }),
        )
    })

    it('shows a setup prompt when no home address is configured', async () => {
        const emptyHome = ref({ address: '', lat: null, lon: null })
        wrapper = mount(CommuteTab, {
            global: {
                provide: { homeAddress: emptyHome },
                stubs: STUBS,
            },
        })
        await new Promise((r) => setTimeout(r, 0))

        expect(wrapper.text()).toContain('Set your home address')
        expect(axios.get).not.toHaveBeenCalledWith('/api/commute/etas', expect.anything())
    })

    it('force refresh hits the refresh endpoint', async () => {
        await mountTab()

        const refreshBtn = wrapper.findAll('button').find((b) => b.text().includes('Refresh'))
        await refreshBtn.trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        expect(axios.post).toHaveBeenCalledWith('/api/commute/refresh', null, {
            params: { home_lat: 28.0631, home_lon: -81.9485 },
        })
    })

    it('starts centered on home at the default zoom with no saved view', async () => {
        await mountTab()

        expect(fakeMap().view).toEqual({ latlng: [28.0631, -81.9485], zoom: 13 })
    })

    it('restores a saved map view anchored to the current home', async () => {
        localStorage.setItem(
            'commute_map_view',
            JSON.stringify({ lat: 28.1, lng: -81.9, zoom: 15, home_lat: 28.0631, home_lon: -81.9485 }),
        )

        await mountTab()

        expect(fakeMap().view).toEqual({ latlng: [28.1, -81.9], zoom: 15 })
    })

    it('ignores a saved map view when the home address changed', async () => {
        localStorage.setItem(
            'commute_map_view',
            JSON.stringify({ lat: 28.1, lng: -81.9, zoom: 15, home_lat: 28.0, home_lon: -82.0 }),
        )

        await mountTab()

        expect(fakeMap().view).toEqual({ latlng: [28.0631, -81.9485], zoom: 13 })
    })

    it('ignores a corrupt saved map view and falls back to home', async () => {
        localStorage.setItem('commute_map_view', 'not json')

        await mountTab()

        expect(fakeMap().view).toEqual({ latlng: [28.0631, -81.9485], zoom: 13 })
    })

    it('persists the adjusted view (with home anchor) after the user moves the map', async () => {
        vi.useFakeTimers()
        try {
            wrapper = mount(CommuteTab, {
                global: { provide: { homeAddress: home }, stubs: STUBS },
            })
            await vi.advanceTimersByTimeAsync(0)
            await vi.advanceTimersByTimeAsync(10)

            const map = fakeMap()
            map.view = { latlng: [28.06, -81.95], zoom: 14 }
            map.handlers.moveend.forEach((h) => h())
            await vi.advanceTimersByTimeAsync(600)

            expect(JSON.parse(localStorage.getItem('commute_map_view'))).toEqual({
                lat: 28.06,
                lng: -81.95,
                zoom: 14,
                home_lat: 28.0631,
                home_lon: -81.9485,
            })
        } finally {
            vi.useRealTimers()
        }
    })

    it('re-centers on home when the focus-home button is tapped', async () => {
        localStorage.setItem(
            'commute_map_view',
            JSON.stringify({ lat: 28.1, lng: -81.9, zoom: 15, home_lat: 28.0631, home_lon: -81.9485 }),
        )
        await mountTab()

        await mapButton('Center on home').trigger('click')

        expect(fakeMap().view).toEqual({ latlng: [28.0631, -81.9485], zoom: 13 })
    })

    it('fits all routes into view with the auto-fit button without persisting it', async () => {
        vi.useFakeTimers()
        try {
            wrapper = mount(CommuteTab, {
                global: { provide: { homeAddress: home }, stubs: STUBS },
            })
            await vi.advanceTimersByTimeAsync(0)
            await vi.advanceTimersByTimeAsync(10)

            const map = fakeMap()
            await mapButton('Fit all routes').trigger('click')

            expect(map.fitBounds).toHaveBeenCalledWith([
                [28.0631, -81.9485],
                [28.0636, -81.9434],
            ])

            map.handlers.moveend.forEach((h) => h())
            await vi.advanceTimersByTimeAsync(600)

            expect(localStorage.getItem('commute_map_view')).toBeNull()
        } finally {
            vi.useRealTimers()
        }
    })
})