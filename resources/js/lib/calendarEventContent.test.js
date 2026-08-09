import { describe, it, expect } from 'vitest'
import { renderEventContent } from './calendarEventContent'

const makeArg = (overrides = {}) => ({
    timeText: '3:00 PM',
    event: { title: 'Soccer Practice', allDay: false },
    view: { type: 'dayGridMonth' },
    ...overrides,
})

describe('renderEventContent', () => {
    it('shows a visible title in the agenda (list) view', () => {
        const { domNodes } = renderEventContent(
            makeArg({ view: { type: 'listWeek' } }),
        )
        const titleEl = domNodes[0]

        expect(titleEl.textContent).toBe('Soccer Practice')
        expect(titleEl.className).toContain('text-slate-900')
        expect(titleEl.className).toContain('dark:text-white/90')
        expect(titleEl.className.split(' ')).not.toContain('text-white')
    })

    it('keeps the white-on-color block styling in grid views', () => {
        const { domNodes } = renderEventContent(makeArg())
        const container = domNodes[0]

        expect(container.className).toContain('text-white')
        expect(container.textContent).toContain('3:00 PM')
        expect(container.textContent).toContain('Soccer Practice')
    })

    it('omits the time row for all-day events in grid views', () => {
        const { domNodes } = renderEventContent(
            makeArg({ event: { title: 'Family Day', allDay: true } }),
        )
        const container = domNodes[0]

        expect(container.textContent).not.toContain('3:00 PM')
        expect(container.textContent).toContain('Family Day')
    })
})
