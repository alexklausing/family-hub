import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import axios from 'axios'
import CommuteWidget from './CommuteWidget.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}))

const home = ref({ address: '1271 Evergreen Park Cir', lat: 28.0631, lon: -81.9485 })

const etaResponse = () => [
    {
        destination: { id: 1, name: 'Lakeland Montessori', lat: 28.0636, lon: -81.9434, icon: 'GraduationCap' },
        route: {
            travel_time_minutes: 12,
            travel_time_seconds: 720,
            traffic_delay_minutes: 0,
            no_traffic_time_minutes: 9,
        },
    },
    {
        destination: { id: 2, name: 'Florida Southern College', lat: 28.0327, lon: -81.9502, icon: 'Building2' },
        route: {
            travel_time_minutes: 24,
            travel_time_seconds: 1440,
            traffic_delay_minutes: 6,
            no_traffic_time_minutes: 18,
        },
    },
]

describe('CommuteWidget', () => {
    let wrapper

    beforeEach(() => {
        vi.clearAllMocks()
        axios.get.mockReset()
        axios.post.mockReset()
        axios.get.mockResolvedValue({ data: etaResponse() })
        axios.post.mockResolvedValue({ data: etaResponse() })
    })

    const mountWidget = async () => {
        wrapper = mount(CommuteWidget, {
            global: {
                provide: { homeAddress: home },
            },
        })
        await new Promise((r) => setTimeout(r, 0))
        await new Promise((r) => setTimeout(r, 0))
    }

    it('renders compact ETA rows with destination names', async () => {
        await mountWidget()

        expect(axios.get).toHaveBeenCalledWith('/api/commute/etas', {
            params: { home_lat: 28.0631, home_lon: -81.9485 },
        })
        expect(wrapper.text()).toContain('Commute')
        expect(wrapper.text()).toContain('Lakeland Montessori')
        expect(wrapper.text()).toContain('Florida Southern College')
        expect(wrapper.text()).toContain('Arrive')
    })

    it('renders a green dot for on-time routes and red for heavy delays', async () => {
        await mountWidget()

        const dots = wrapper.findAll('.rounded-full')
        const greenDot = dots.find((d) => d.classes().includes('bg-green-500'))
        const redDot = dots.find((d) => d.classes().includes('bg-red-500'))
        expect(greenDot).toBeTruthy()
        expect(redDot).toBeTruthy()
    })

    it('shows a prompt when no home address is set', async () => {
        const emptyHome = ref({ address: '', lat: null, lon: null })
        wrapper = mount(CommuteWidget, {
            global: {
                provide: { homeAddress: emptyHome },
            },
        })
        await new Promise((r) => setTimeout(r, 0))

        expect(wrapper.text()).toContain('Set your home address')
        expect(axios.get).not.toHaveBeenCalled()
    })
})