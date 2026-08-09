export function renderEventContent(arg) {
    const timeText = arg.timeText
    const title = arg.event.title
    const isAllDay = arg.event.allDay

    // The list (agenda) view renders event content on a plain light/dark
    // row, so we can't use the white-on-color block styling used in
    // calendar grid views.
    if (arg.view.type.startsWith('list')) {
        const titleEl = document.createElement('div')
        titleEl.className =
            'truncate font-bold text-[11px] uppercase tracking-tight text-slate-900 dark:text-white/90'
        titleEl.textContent = title
        return { domNodes: [titleEl] }
    }

    const container = document.createElement('div')
    container.className =
        'flex flex-col gap-0.5 leading-tight overflow-hidden py-0.5 px-1 text-white'

    if (!isAllDay && timeText) {
        const timeEl = document.createElement('div')
        timeEl.className =
            'text-[9px] font-black uppercase tracking-tighter opacity-80 mb-0.5'
        timeEl.textContent = timeText
        container.appendChild(timeEl)
    }

    const titleEl = document.createElement('div')
    titleEl.className = 'font-bold text-[10px] truncate'
    titleEl.textContent = title
    container.appendChild(titleEl)

    return { domNodes: [container] }
}
