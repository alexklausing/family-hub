import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import axios from 'axios'
import WeatherTab from './WeatherTab.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}))

const chainable = () => {
    const target = {}
    const handler = {
        get: (obj, prop) => {
            if (prop === 'then') return undefined
            return (...args) => {
                const result = chainable()
                obj._calls = obj._calls || []
                obj._calls.push(prop)
                return result
            }
        },
        apply: () => chainable(),
    }
    return new Proxy(target, handler)
}

const tileLayerMock = vi.fn(() => chainable())

vi.mock('leaflet', () => ({
    default: {
        map: vi.fn(() => chainable()),
        tileLayer: (...args) => tileLayerMock(...args),
        divIcon: vi.fn(() => ({})),
        marker: vi.fn(() => chainable()),
        geoJSON: vi.fn(() => chainable()),
        circleMarker: vi.fn(() => chainable()),
        DomEvent: { stopPropagation: vi.fn() },
    },
}))

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

const weatherResponse = () => ({
    data: {
        weather: {
            current: {
                dt: 100,
                temp: 72,
                wind_speed: 5,
                humidity: 50,
                feels_like: 70,
                sunrise: 100,
                sunset: 200,
                weather: [{ main: 'clear', description: 'clear sky' }],
            },
            hourly: [],
            daily: [],
        },
        alerts: [],
        air_quality: 42,
        iss: null,
        launch: null,
        location: { lat: 28.0395, lon: -81.9498 },
        apiKey: null,
    },
})

describe('WeatherTab', () => {
    let wrapper

    beforeEach(() => {
        tileLayerMock.mockClear()
        axios.get.mockReset()
        axios.get.mockResolvedValue(weatherResponse())
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })
    })

    const mountTab = async () => {
        wrapper = mount(WeatherTab, {
            global: {
                provide: {
                    weatherView: ref('weather'),
                    developerSettings: ref({}),
                },
                stubs: {
                    'Card': { template: '<div><slot /></div>' },
                    'CardContent': { template: '<div><slot /></div>' },
                    'CardHeader': { template: '<div><slot /></div>' },
                    'CardTitle': { template: '<div><slot /></div>' },
                    'Dialog': { template: '<div><slot /></div>' },
                    'DialogContent': { template: '<div><slot /></div>' },
                    'DialogHeader': { template: '<div><slot /></div>' },
                    'DialogTitle': { template: '<div><slot /></div>' },
                    'DialogDescription': { template: '<div><slot /></div>' },
                    'Button': { template: '<button><slot /></button>' },
                },
            },
        })
        await new Promise((r) => setTimeout(r, 0))
        await new Promise((r) => setTimeout(r, 0))
    }

    it('creates the OSM base layer with referrerPolicy origin so tiles send a Referer', async () => {
        await mountTab()

        expect(tileLayerMock).toHaveBeenCalledWith(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            expect.objectContaining({ referrerPolicy: 'origin', maxZoom: 19 }),
        )
    })

    it('creates mesonet radar layers with timestamped tile URLs', async () => {
        await mountTab()

        const radarCalls = tileLayerMock.mock.calls.filter(
            ([url]) => url && url.includes('ridge::USCOMP-N0Q-'),
        )
        expect(radarCalls.length).toBe(6)
        expect(radarCalls[5][1]).toEqual(expect.objectContaining({ opacity: 0.7 }))
    })
})
