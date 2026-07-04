<script setup>
import { ref, watch, onMounted, computed, nextTick } from 'vue'
import confetti from 'canvas-confetti'
import axios from 'axios'
import { useIdle } from '@vueuse/core'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import {
    Plus,
    Edit2,
    Trash2,
    CheckCircle2,
    Copy,
    Lock,
    Unlock,
    Tag,
    Gift,
    DollarSign,
    X,
    Check,
    ClipboardCopy,
    ChevronDown,
    Wallet,
    Inbox,
    Clock,
    Delete,
} from 'lucide-vue-next'

const props = defineProps({ profiles: Array, activeProfile: String })
const emit = defineEmits(['update:activeProfile'])

// ── State ──────────────────────────────────────────────────────────────────
const chores = ref([])
const isLoading = ref(false)
const labels = ref([])

// PIN / Unlock
const PARENTAL_PIN = '1234'
const isUnlocked = ref(false)
const isPinPromptOpen = ref(false)
const enteredPin = ref('')
const pinError = ref(false)
const { idle } = useIdle(120 * 1000)
watch(idle, (v) => {
    if (v) isUnlocked.value = false
})

// Chore modal
const isManageModalOpen = ref(false)
const editingChore = ref(null)
const blankChore = () => ({
    title: '',
    profile: props.activeProfile,
    time: '',
    days: [0, 1, 2, 3, 4, 5, 6],
    reward: '',
    is_bankable: true,
    label_id: null,
})
const newChore = ref(blankChore())

// Reward UI state (inside chore modal)
const rewardType = ref('monetary') // 'monetary' | 'text'
const monetaryAmount = ref('') // e.g. "0.50"
const textRewardValue = ref('') // selected/typed text reward
const showRewardLibraryPicker = ref(false)
const isRewardExpanded = ref(false)

// Text Reward Library (localStorage)
const REWARD_LIB_KEY = 'chore_reward_library'
const DEFAULT_TEXT_REWARDS = [
    '30 min screen time',
    '1 hr screen time',
    "Pick tonight's movie",
    'Stay up 30 min late',
    'Pick dessert',
    'Skip a chore tomorrow',
    'Choose family dinner',
    'Extra game time',
]
const textRewardLibrary = ref([])
const newLibraryRewardInput = ref('')

const loadRewardLibrary = () => {
    try {
        const stored = localStorage.getItem(REWARD_LIB_KEY)
        textRewardLibrary.value = stored
            ? JSON.parse(stored)
            : [...DEFAULT_TEXT_REWARDS]
    } catch {
        textRewardLibrary.value = [...DEFAULT_TEXT_REWARDS]
    }
}
const saveRewardLibrary = () => {
    localStorage.setItem(
        REWARD_LIB_KEY,
        JSON.stringify(textRewardLibrary.value),
    )
}
const addLibraryReward = () => {
    const val = newLibraryRewardInput.value.trim()
    if (!val || textRewardLibrary.value.includes(val)) return
    textRewardLibrary.value.push(val)
    saveRewardLibrary()
    newLibraryRewardInput.value = ''
}
const removeLibraryReward = (item) => {
    textRewardLibrary.value = textRewardLibrary.value.filter((r) => r !== item)
    saveRewardLibrary()
}
const pickLibraryReward = (item) => {
    textRewardValue.value = item
    showRewardLibraryPicker.value = false
}
const saveTextRewardToLibrary = () => {
    const val = textRewardValue.value.trim()
    if (!val || textRewardLibrary.value.includes(val)) return
    textRewardLibrary.value.push(val)
    saveRewardLibrary()
}

// Sync reward fields → newChore.reward
watch([rewardType, monetaryAmount, textRewardValue], () => {
    if (rewardType.value === 'monetary') {
        const n = parseFloat(monetaryAmount.value)
        newChore.value.reward =
            monetaryAmount.value && !isNaN(n) ? `$${n.toFixed(2)}` : ''
        newChore.value.is_bankable = true
    } else {
        newChore.value.reward = textRewardValue.value.trim()
    }
})

// Time selector state & helpers
const choreTimeHour = ref(null)
const choreTimeMinute = ref('00')
const choreTimePeriod = ref('AM')
const minutesList = [
    '00',
    '05',
    '10',
    '15',
    '20',
    '25',
    '30',
    '35',
    '40',
    '45',
    '50',
    '55',
]

const computedMinutesList = computed(() => {
    const list = [...minutesList]
    if (choreTimeMinute.value && !list.includes(choreTimeMinute.value)) {
        list.push(choreTimeMinute.value)
        list.sort((a, b) => parseInt(a) - parseInt(b))
    }
    return list
})

// Sync time selector values back to newChore.time
watch([choreTimeHour, choreTimeMinute, choreTimePeriod], () => {
    if (choreTimeHour.value === null) {
        newChore.value.time = ''
        return
    }
    let h = parseInt(choreTimeHour.value)
    if (choreTimePeriod.value === 'PM' && h < 12) h += 12
    if (choreTimePeriod.value === 'AM' && h === 12) h = 0
    const padH = String(h).padStart(2, '0')
    const padM = String(choreTimeMinute.value || '00').padStart(2, '0')
    newChore.value.time = `${padH}:${padM}`
})

// Decode 24h format time (HH:MM) to 12h fields for modal UI
const decodeChoreTime = (timeStr) => {
    if (!timeStr) {
        choreTimeHour.value = null
        choreTimeMinute.value = '00'
        choreTimePeriod.value = 'AM'
        return
    }
    const parts = timeStr.split(':')
    if (parts.length !== 2) return
    let h = parseInt(parts[0])
    const m = parts[1]

    const period = h >= 12 ? 'PM' : 'AM'
    choreTimePeriod.value = period

    h = h % 12
    if (h === 0) h = 12
    choreTimeHour.value = h
    choreTimeMinute.value = m
}

// Scroll container template refs for scrollable wheels
const hoursScrollContainer = ref(null)
const minutesScrollContainer = ref(null)
const periodScrollContainer = ref(null)

const isProgrammaticScroll = ref(false)

const scrollToSelectedTime = () => {
    isProgrammaticScroll.value = true
    nextTick(() => {
        if (choreTimeHour.value !== null) {
            const hourIdx = choreTimeHour.value - 1
            if (hoursScrollContainer.value) {
                hoursScrollContainer.value.scrollTop = hourIdx * 48
            }

            const minIdx = computedMinutesList.value.indexOf(
                choreTimeMinute.value,
            )
            if (minIdx !== -1 && minutesScrollContainer.value) {
                minutesScrollContainer.value.scrollTop = minIdx * 48
            }

            const periodIdx = choreTimePeriod.value === 'PM' ? 1 : 0
            if (periodScrollContainer.value) {
                periodScrollContainer.value.scrollTop = periodIdx * 48
            }
        } else {
            if (hoursScrollContainer.value)
                hoursScrollContainer.value.scrollTop = 0
            if (minutesScrollContainer.value)
                minutesScrollContainer.value.scrollTop = 0
            if (periodScrollContainer.value)
                periodScrollContainer.value.scrollTop = 0
        }

        setTimeout(() => {
            isProgrammaticScroll.value = false
        }, 150)
    })
}

// Scroll event handlers to update state dynamically on wheel scroll (with debounce to prevent DOM rendering interrupting active scrolls)
let hoursTimeout = null
let minutesTimeout = null
let periodTimeout = null

const onHoursScroll = (e) => {
    if (isProgrammaticScroll.value) return
    clearTimeout(hoursTimeout)
    hoursTimeout = setTimeout(() => {
        const scrollTop = e.target.scrollTop
        const index = Math.round(scrollTop / 48)
        const val = index + 1
        if (val >= 1 && val <= 12 && choreTimeHour.value !== val) {
            choreTimeHour.value = val
        }
    }, 150)
}

const onMinutesScroll = (e) => {
    if (isProgrammaticScroll.value) return
    clearTimeout(minutesTimeout)
    minutesTimeout = setTimeout(() => {
        const scrollTop = e.target.scrollTop
        const index = Math.round(scrollTop / 48)
        const list = computedMinutesList.value
        if (index >= 0 && index < list.length) {
            const val = list[index]
            if (choreTimeMinute.value !== val) {
                choreTimeMinute.value = val
            }
        }
    }, 150)
}

const onPeriodScroll = (e) => {
    if (isProgrammaticScroll.value) return
    clearTimeout(periodTimeout)
    periodTimeout = setTimeout(() => {
        const scrollTop = e.target.scrollTop
        const index = Math.round(scrollTop / 48)
        const val = index === 1 ? 'PM' : 'AM'
        if (choreTimePeriod.value !== val) {
            choreTimePeriod.value = val
        }
    }, 150)
}

const selectHour = (h) => {
    choreTimeHour.value = h
    scrollToSelectedTime()
}

const selectMinute = (m) => {
    choreTimeMinute.value = m
    scrollToSelectedTime()
}

const selectPeriod = (p) => {
    choreTimePeriod.value = p
    scrollToSelectedTime()
}

const enableTimeLimit = () => {
    choreTimeHour.value = 12
    choreTimeMinute.value = '00'
    choreTimePeriod.value = 'AM'
    scrollToSelectedTime()
}

// Sync scroll positions when modal opens
watch(isManageModalOpen, (newVal) => {
    if (newVal) {
        scrollToSelectedTime()
    }
})

// When opening modal, decode existing reward back into fields
const decodeRewardIntoFields = (reward) => {
    if (!reward) {
        rewardType.value = 'monetary'
        monetaryAmount.value = ''
        textRewardValue.value = ''
        return
    }
    const match = reward.match(/^\$(\d+\.?\d*)$/)
    if (match) {
        rewardType.value = 'monetary'
        monetaryAmount.value = match[1]
        textRewardValue.value = ''
    } else {
        rewardType.value = 'text'
        monetaryAmount.value = ''
        textRewardValue.value = reward
    }
}
const clearReward = () => {
    monetaryAmount.value = ''
    textRewardValue.value = ''
}

// Label modal state
const isLabelsModalOpen = ref(false)
const isLabelFormOpen = ref(false)
const newLabelName = ref('')
const newLabelReward = ref('')
const newLabelIsBankable = ref(true)
const labelRewardType = ref('monetary')
const labelMonetaryAmount = ref('')
const labelTextReward = ref('')
const editingLabel = ref(null)

const labelHasBonus = ref(false)
const labelBonusDays = ref([0, 1, 2, 3, 4])
const labelBonusValue = ref('')
const labelBonusExpires = ref(3)
const labelBonusRequiresApproval = ref(true)

const toggleLabelBonusDay = (v) => {
    const i = labelBonusDays.value.indexOf(v)
    if (i === -1) labelBonusDays.value.push(v)
    else labelBonusDays.value.splice(i, 1)
}

// Sync label reward fields
watch([labelRewardType, labelMonetaryAmount, labelTextReward], () => {
    if (labelRewardType.value === 'monetary') {
        const n = parseFloat(labelMonetaryAmount.value)
        newLabelReward.value =
            labelMonetaryAmount.value && !isNaN(n) ? `$${n.toFixed(2)}` : ''
        newLabelIsBankable.value = true
    } else {
        newLabelReward.value = labelTextReward.value.trim()
    }
})

watch(isLabelFormOpen, async (newVal, oldVal) => {
    if (!newVal && oldVal) {
        if (newLabelName.value.trim()) {
            await saveLabel()
        }
    }
})

watch(
    () => newChore.value.is_bankable,
    (newVal) => {
        if (!newVal) {
            choreHasBonus.value = false
            choreBonusDays.value = [0, 1, 2, 3, 4]
            choreBonusValue.value = ''
            choreBonusExpires.value = 3
            choreBonusRequiresApproval.value = true
        }
    },
)

watch(newLabelIsBankable, (newVal) => {
    if (!newVal) {
        resetLabelBonusFields()
    }
})

const selectedLabel = computed(() => {
    return labels.value.find((l) => l.id === newChore.value.label_id)
})

const selectedLabelHasReward = computed(() => {
    return !!(selectedLabel.value?.reward || selectedLabel.value?.bonus_reward)
})

const formatRequiredDays = (days) => {
    if (!days || !days.length) return 'None'
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    return days.map((d) => dayNames[d]).join(', ')
}

watch(selectedLabelHasReward, (newVal) => {
    if (newVal) {
        clearReward()
        choreHasBonus.value = false
        choreBonusDays.value = [0, 1, 2, 3, 4]
        choreBonusValue.value = ''
        choreBonusExpires.value = 3
        choreBonusRequiresApproval.value = true
    }
})

const decodeLabelReward = (reward) => {
    if (!reward) {
        labelRewardType.value = 'monetary'
        labelMonetaryAmount.value = ''
        labelTextReward.value = ''
        return
    }
    const match = reward?.match(/^\$(\d+\.?\d*)$/)
    if (match) {
        labelRewardType.value = 'monetary'
        labelMonetaryAmount.value = match[1]
        labelTextReward.value = ''
    } else {
        labelRewardType.value = 'text'
        labelMonetaryAmount.value = ''
        labelTextReward.value = reward
    }
}

const daysOfWeek = [
    { label: 'Su', value: 0 },
    { label: 'M', value: 1 },
    { label: 'Tu', value: 2 },
    { label: 'W', value: 3 },
    { label: 'Th', value: 4 },
    { label: 'F', value: 5 },
    { label: 'Sa', value: 6 },
]

// ── Data Fetching ───────────────────────────────────────────────────────────
const fetchChores = async () => {
    isLoading.value = true
    try {
        const r = await axios.get('/api/chores', {
            params: {
                profile: props.activeProfile,
                date: new Date().toLocaleDateString('en-CA'),
            },
        })
        chores.value = r.data
    } catch (e) {
        console.error(e)
    } finally {
        isLoading.value = false
    }
}

const fetchLabels = async () => {
    try {
        labels.value = (await axios.get('/api/labels')).data
    } catch (e) {
        console.error(e)
    }
}

watch(
    () => props.activeProfile,
    () => {
        newChore.value.profile = props.activeProfile
        fetchChores()
    },
)
onMounted(() => {
    fetchChores()
    fetchLabels()
    loadRewardLibrary()
})

// ── Confetti ────────────────────────────────────────────────────────────────
const showBoardCelebration = ref(false)
const celebrationVisible = ref(false) // drives the CSS opacity transition

/** Level 1 — Single chore ticked: tiny localised pop */
const fireTaskConfetti = () => {
    confetti({
        particleCount: 25,
        spread: 40,
        startVelocity: 28,
        gravity: 1.1,
        ticks: 120,
        origin: { x: 0.5, y: 0.55 },
        colors: ['#a855f7', '#ec4899', '#f59e0b'],
        scalar: 0.8,
    })
}

/** Level 2 — Full card/group cleared: cheerful dual-cannon from the sides */
const fireGroupConfetti = () => {
    const base = {
        particleCount: 70,
        spread: 60,
        startVelocity: 50,
        gravity: 0.85,
        ticks: 220,
        colors: ['#a855f7', '#ec4899', '#f59e0b', '#10b981'],
    }
    confetti({ ...base, origin: { x: 0.15, y: 0.8 }, angle: 60 })
    confetti({ ...base, origin: { x: 0.85, y: 0.8 }, angle: 120 })
}

/** Level 3 — Full board cleared: sustained cannons + star burst + name overlay */
const fireBoardConfetti = () => {
    const duration = 4000
    const end = Date.now() + duration
    const colors = ['#a855f7', '#ec4899', '#f59e0b', '#10b981', '#3b82f6']

    // Show the name overlay
    showBoardCelebration.value = true
    // Small delay so the element is mounted before we transition opacity in
    setTimeout(() => {
        celebrationVisible.value = true
    }, 30)
    // Fade out and remove after the confetti ends
    setTimeout(() => {
        celebrationVisible.value = false
    }, duration - 600)
    setTimeout(() => {
        showBoardCelebration.value = false
    }, duration)

    // Continuous side cannons
    const frame = () => {
        confetti({
            particleCount: 7,
            angle: 60,
            spread: 55,
            origin: { x: 0, y: 0.65 },
            colors,
            startVelocity: 65,
            gravity: 0.75,
        })
        confetti({
            particleCount: 7,
            angle: 120,
            spread: 55,
            origin: { x: 1, y: 0.65 },
            colors,
            startVelocity: 65,
            gravity: 0.75,
        })
        if (Date.now() < end) requestAnimationFrame(frame)
    }
    frame()

    // Big star burst from top-centre
    setTimeout(() => {
        confetti({
            particleCount: 160,
            spread: 110,
            origin: { x: 0.5, y: 0.25 },
            colors,
            startVelocity: 65,
            shapes: ['star', 'circle'],
            scalar: 1.4,
            ticks: 300,
        })
    }, 350)

    // Second wave from centre-bottom
    setTimeout(() => {
        confetti({
            particleCount: 80,
            spread: 120,
            origin: { x: 0.5, y: 0.9 },
            colors,
            startVelocity: 45,
            gravity: 0.6,
            ticks: 250,
        })
    }, 900)
}

// ── Chore Completion ────────────────────────────────────────────────────────
const toggleChore = async (chore) => {
    const wasCompleted = chore.completed
    chore.completed = !chore.completed
    try {
        await axios.post(`/api/chores/${chore.id}/toggle`, {
            date: new Date().toLocaleDateString('en-CA'),
        })

        // Celebrations only fire when ticking complete, not un-ticking
        if (!wasCompleted) {
            await nextTick()
            const allChores = todaysChores.value
            const boardDone =
                allChores.length > 0 && allChores.every((c) => c.completed)
            const groupDone = groupedChores.value.some(
                (g) =>
                    g.chores.includes(chore) &&
                    g.chores.every((c) => c.completed),
            )

            if (boardDone) {
                // Level 3 — skip lower tiers, go straight to full celebration
                setTimeout(fireBoardConfetti, 150)
            } else if (groupDone) {
                // Level 2 — group finished
                setTimeout(fireGroupConfetti, 100)
            } else {
                // Level 1 — single task
                setTimeout(fireTaskConfetti, 50)
            }
        }
    } catch (e) {
        console.error(e)
        chore.completed = !chore.completed
    }
}

// ── PIN ─────────────────────────────────────────────────────────────────────
const promptUnlock = () => {
    enteredPin.value = ''
    pinError.value = false
    isPinPromptOpen.value = true
}
const lockPortal = () => {
    isUnlocked.value = false
}
const verifyPin = () => {
    if (enteredPin.value === PARENTAL_PIN) {
        isPinPromptOpen.value = false
        isUnlocked.value = true
        enteredPin.value = ''
    } else {
        pinError.value = true
        enteredPin.value = ''
    }
}

const appendPin = (num) => {
    pinError.value = false
    if (enteredPin.value.length < 4) {
        enteredPin.value += num
    }
    if (enteredPin.value.length === 4) {
        verifyPin()
    }
}

// ── Chore CRUD ───────────────────────────────────────────────────────────────
const choreHasBonus = ref(false)
const choreBonusDays = ref([0, 1, 2, 3, 4])
const choreBonusValue = ref('')
const choreBonusExpires = ref(3)
const choreBonusRequiresApproval = ref(true)

const toggleChoreBonusDay = (v) => {
    const i = choreBonusDays.value.indexOf(v)
    if (i === -1) choreBonusDays.value.push(v)
    else choreBonusDays.value.splice(i, 1)
}

const openAddModal = () => {
    editingChore.value = null
    newChore.value = blankChore()
    decodeRewardIntoFields('')
    decodeChoreTime('')
    showRewardLibraryPicker.value = false
    choreHasBonus.value = false
    choreBonusDays.value = [0, 1, 2, 3, 4]
    choreBonusValue.value = ''
    choreBonusExpires.value = 3
    choreBonusRequiresApproval.value = true
    isRewardExpanded.value = false
    isManageModalOpen.value = true
}
const openEditModal = (chore) => {
    editingChore.value = chore
    newChore.value = {
        title: chore.title,
        profile: chore.profile,
        time: chore.time || '',
        days: chore.days || [0, 1, 2, 3, 4, 5, 6],
        reward: chore.reward || '',
        is_bankable: chore.is_bankable ?? true,
        label_id: chore.label_id || null,
    }
    decodeRewardIntoFields(chore.reward)
    decodeChoreTime(chore.time)
    showRewardLibraryPicker.value = false

    if (chore.bonus_reward) {
        choreHasBonus.value = true
        choreBonusDays.value = chore.bonus_reward.required_days || []
        choreBonusValue.value = chore.bonus_reward.reward_value || ''
        choreBonusExpires.value = chore.bonus_reward.expires_in_days || 3
        choreBonusRequiresApproval.value =
            chore.bonus_reward.requires_approval ?? true
    } else {
        choreHasBonus.value = false
        choreBonusDays.value = [0, 1, 2, 3, 4]
        choreBonusValue.value = ''
        choreBonusExpires.value = 3
        choreBonusRequiresApproval.value = true
    }

    isRewardExpanded.value = !!chore.reward
    isManageModalOpen.value = true
}
const openDuplicateModal = (chore) => {
    editingChore.value = null
    newChore.value = {
        title: chore.title,
        profile: chore.profile,
        time: chore.time || '',
        days: chore.days ? [...chore.days] : [0, 1, 2, 3, 4, 5, 6],
        reward: chore.reward || '',
        is_bankable: chore.is_bankable ?? true,
        label_id: chore.label_id || null,
    }
    decodeRewardIntoFields(chore.reward)
    decodeChoreTime(chore.time)
    showRewardLibraryPicker.value = false
    isRewardExpanded.value = !!chore.reward
    isManageModalOpen.value = true
}
const saveChore = async () => {
    try {
        const payload = {
            ...newChore.value,
            label_id: newChore.value.label_id || null,
        }

        if (
            newChore.value.is_bankable &&
            choreHasBonus.value &&
            choreBonusValue.value
        ) {
            payload.bonus_reward = {
                required_days: choreBonusDays.value,
                reward_value: choreBonusValue.value,
                expires_in_days: choreBonusExpires.value || null,
                requires_approval: choreBonusRequiresApproval.value,
            }
        } else {
            payload.bonus_reward = null
        }
        if (editingChore.value)
            await axios.put(`/api/chores/${editingChore.value.id}`, payload)
        else await axios.post('/api/chores', payload)
        isManageModalOpen.value = false
        fetchChores()
    } catch (e) {
        console.error(e)
    }
}
const deleteChore = async (chore) => {
    if (!confirm(`Delete "${chore.title}"?`)) return
    try {
        await axios.delete(`/api/chores/${chore.id}`)
        fetchChores()
    } catch (e) {
        console.error(e)
    }
}
const toggleDay = (v) => {
    const i = newChore.value.days.indexOf(v)
    if (i === -1) newChore.value.days.push(v)
    else newChore.value.days.splice(i, 1)
}

// ── Clone Group ──────────────────────────────────────────────────────────────
const isCloneModalOpen = ref(false)
const cloneTargetGroup = ref(null) // { label, chores }
const cloneToProfile = ref('')
const cloneMode = ref('skip') // 'skip' | 'replace'
const isCloning = ref(false)
const cloneResult = ref(null) // { cloned, skipped, message } | null

const openCloneModal = (group) => {
    cloneTargetGroup.value = group
    // Default to first profile that isn't current
    const others = props.profiles.filter((p) => p.name !== props.activeProfile)
    cloneToProfile.value = others[0]?.name || ''
    cloneMode.value = 'skip'
    cloneResult.value = null
    isCloneModalOpen.value = true
}

const executeCloneGroup = async () => {
    if (!cloneToProfile.value || isCloning.value) return
    isCloning.value = true
    cloneResult.value = null
    try {
        const group = cloneTargetGroup.value
        const payload = {
            from_profile: props.activeProfile,
            to_profile: cloneToProfile.value,
            label_id: group.label ? group.label.id : null,
            mode: cloneMode.value,
        }
        const r = await axios.post('/api/chores/clone-group', payload)
        cloneResult.value = r.data
        fetchChores()
    } catch (e) {
        console.error(e)
        cloneResult.value = { message: 'An error occurred. Please try again.' }
    } finally {
        isCloning.value = false
    }
}

// ── Label CRUD ───────────────────────────────────────────────────────────────
const resetLabelBonusFields = () => {
    labelHasBonus.value = false
    labelBonusDays.value = [0, 1, 2, 3, 4]
    labelBonusValue.value = ''
    labelBonusExpires.value = 3
    labelBonusRequiresApproval.value = true
}
const openLabelsModal = () => {
    editingLabel.value = null
    newLabelName.value = ''
    decodeLabelReward('')
    newLabelIsBankable.value = true
    resetLabelBonusFields()
    isLabelsModalOpen.value = true
}
const startCreateLabel = () => {
    editingLabel.value = null
    newLabelName.value = ''
    decodeLabelReward('')
    newLabelIsBankable.value = true
    resetLabelBonusFields()
    isLabelFormOpen.value = true
}
const startEditLabel = (label) => {
    editingLabel.value = label
    newLabelName.value = label.name
    decodeLabelReward(label.reward)
    newLabelIsBankable.value = label.is_bankable ?? true

    if (label.bonus_reward) {
        labelHasBonus.value = true
        labelBonusDays.value = label.bonus_reward.required_days || []
        labelBonusValue.value = label.bonus_reward.reward_value || ''
        labelBonusExpires.value = label.bonus_reward.expires_in_days || 3
        labelBonusRequiresApproval.value =
            label.bonus_reward.requires_approval ?? true
    } else {
        resetLabelBonusFields()
    }
    isLabelFormOpen.value = true
}
const cancelEditLabel = () => {
    newLabelName.value = ''
    editingLabel.value = null
    decodeLabelReward('')
    newLabelIsBankable.value = true
    isLabelFormOpen.value = false
}
const saveLabel = async () => {
    if (!newLabelName.value.trim()) return
    try {
        const payload = {
            name: newLabelName.value,
            reward: newLabelReward.value || null,
            is_bankable: newLabelIsBankable.value,
        }

        if (
            newLabelIsBankable.value &&
            labelHasBonus.value &&
            labelBonusValue.value
        ) {
            payload.bonus_reward = {
                required_days: labelBonusDays.value,
                reward_value: labelBonusValue.value,
                expires_in_days: labelBonusExpires.value || null,
                requires_approval: labelBonusRequiresApproval.value,
            }
        } else {
            payload.bonus_reward = null
        }
        if (editingLabel.value) {
            const r = await axios.put(
                `/api/labels/${editingLabel.value.id}`,
                payload,
            )
            const idx = labels.value.findIndex(
                (l) => l.id === editingLabel.value.id,
            )
            if (idx !== -1) labels.value[idx] = r.data
        } else {
            labels.value.push((await axios.post('/api/labels', payload)).data)
        }
        cancelEditLabel()
        fetchChores()
    } catch (e) {
        console.error(e)
    }
}
const deleteLabel = async (label) => {
    if (
        !confirm(
            `Delete label "${label.name}"? Chores using it will become unlabeled.`,
        )
    )
        return
    try {
        await axios.delete(`/api/labels/${label.id}`)
        labels.value = labels.value.filter((l) => l.id !== label.id)
        fetchChores()
    } catch (e) {
        console.error(e)
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
const formatTime = (t) => {
    if (!t) return ''
    const [h, m] = t.split(':')
    const hr = parseInt(h)
    return `${hr % 12 || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`
}
const rewardIsMonetary = (r) => r && /^\$/.test(r.trim())
const formatRewardText = (r) => (r ? r.replace(/^\$/, '') : '')
const getChoreReward = (chore) => chore.reward || chore.label?.reward || null

const todaysChores = computed(() => {
    const day = new Date().getDay()
    return chores.value.filter((c) => !c.days || c.days.includes(day))
})

// Grouped by label — labelled groups first (ordered by first chore's order),
// then a catch-all "General" group for unlabelled chores.
const groupedChores = computed(() => {
    const all = todaysChores.value
    const labelMap = {} // label_id → group
    const unlabelled = []

    all.forEach((c) => {
        if (c.label) {
            const id = c.label.id
            if (!labelMap[id]) {
                labelMap[id] = {
                    key: `label-${id}`,
                    label: c.label,
                    chores: [],
                }
            }
            labelMap[id].chores.push(c)
        } else {
            unlabelled.push(c)
        }
    })

    const groups = Object.values(labelMap)
    if (unlabelled.length) {
        groups.push({ key: 'unlabelled', label: null, chores: unlabelled })
    }
    return groups
})

// ── Bank & Approvals ─────────────────────────────────────────────────────────
const isBankModalOpen = ref(false)
const bankBalance = ref(0)
const bankTextRewards = ref([])
const dailyBankTextRewards = ref([])
const pendingBankBalance = ref(0)
const pendingBankTextRewards = ref([])
const bankHistory = ref([])

const fetchBank = async () => {
    try {
        const r = await axios.get('/api/rewards/bank', {
            params: { profile: props.activeProfile },
        })
        bankBalance.value = r.data.monetary_balance
        bankTextRewards.value = r.data.textual_rewards
        dailyBankTextRewards.value = r.data.daily_rewards || []
        pendingBankBalance.value = r.data.pending_monetary_balance
        pendingBankTextRewards.value = r.data.pending_textual_rewards
        bankHistory.value = r.data.history
    } catch (e) {
        console.error(e)
    }
}

const openBankModal = () => {
    fetchBank()
    isBankModalOpen.value = true
}

const isRedeemModalOpen = ref(false)
const redeemPayload = ref(null)

const confirmRedeemReward = (type, amount, text = null, isDaily = false) => {
    if (!isUnlocked.value) {
        promptUnlock()
        return
    }
    redeemPayload.value = { type, amount, text, isDaily }
    isRedeemModalOpen.value = true
}

const executeRedeemReward = async () => {
    if (!redeemPayload.value) return
    try {
        await axios.post('/api/rewards/redeem', {
            profile: props.activeProfile,
            type: redeemPayload.value.type,
            amount: redeemPayload.value.amount,
            reward_text: redeemPayload.value.text,
            is_daily: redeemPayload.value.isDaily,
        })
        fetchBank()
        isRedeemModalOpen.value = false
        redeemPayload.value = null
    } catch (e) {
        console.error(e)
    }
}

const isApprovalsModalOpen = ref(false)
const pendingApprovals = ref([])

const fetchApprovals = async () => {
    try {
        const r = await axios.get('/api/chores/approvals')
        pendingApprovals.value = r.data.map((c) => ({
            ...c,
            awarded_value:
                c.is_bonus || c.is_label
                    ? c.awarded_value || ''
                    : getChoreReward(c.chore) || '',
        }))
    } catch (e) {
        console.error(e)
    }
}

const openApprovalsModal = () => {
    fetchApprovals()
    isApprovalsModalOpen.value = true
}

const processApproval = async (completion, action) => {
    try {
        await axios.post(`/api/chores/approvals/${completion.id}`, {
            action,
            awarded_value:
                completion.awarded_value !== undefined
                    ? completion.awarded_value
                    : getChoreReward(completion.chore),
        })
        fetchApprovals()
        fetchChores() // refresh status
    } catch (e) {
        console.error(e)
    }
}

watch(
    () => props.activeProfile,
    () => {
        if (isBankModalOpen.value) fetchBank()
    },
)
</script>

<template>
    <div class="flex h-full flex-col gap-4 p-2 lg:p-4">
        <!-- ── Board Celebration Overlay ── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-500"
                leave-active-class="transition-all duration-700"
                enter-from-class="opacity-0 scale-90"
                leave-to-class="opacity-0 scale-110"
            >
                <div
                    v-if="showBoardCelebration && celebrationVisible"
                    class="pointer-events-none fixed inset-0 z-[9999] flex flex-col items-center justify-center"
                >
                    <!-- Blurred backdrop -->
                    <div
                        class="absolute inset-0 bg-black/20 backdrop-blur-sm"
                    />
                    <!-- Text card -->
                    <div
                        class="relative flex flex-col items-center gap-4 rounded-[3rem] bg-white/20 px-16 py-12 text-center shadow-2xl backdrop-blur-2xl dark:bg-black/30"
                    >
                        <div class="text-7xl leading-none">🎉</div>
                        <h2
                            class="bg-gradient-to-r from-violet-400 via-pink-400 to-amber-400 bg-clip-text text-6xl leading-none font-black text-transparent"
                        >
                            Great job, {{ activeProfile }}!
                        </h2>
                        <p class="text-lg font-semibold text-white/60">
                            All done for today ✨
                        </p>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Profile + Action Bar -->
        <div
            class="flex items-center gap-2 overflow-x-auto rounded-3xl bg-white/40 p-2 backdrop-blur-2xl dark:bg-white/5"
        >
            <Button
                v-for="profile in profiles"
                :key="profile.name"
                variant="ghost"
                class="flex h-14 min-w-[100px] flex-col items-center justify-center gap-1 rounded-2xl transition-all"
                :class="
                    activeProfile === profile.name
                        ? 'bg-primary text-primary-foreground shadow-lg'
                        : 'hover:bg-white/50 dark:hover:bg-white/10'
                "
                @click="emit('update:activeProfile', profile.name)"
            >
                <component :is="profile.icon" class="h-5 w-5" />
                <span class="text-xs font-bold">{{ profile.name }}</span>
            </Button>
            <div class="flex-1" />
            <Button
                variant="outline"
                class="h-14 shrink-0 rounded-2xl bg-white/50 px-4 font-bold"
                @click="openBankModal"
                title="Wallet / Bank"
            >
                <Wallet class="mr-2 h-5 w-5 text-amber-500" /> Bank
            </Button>
            <template v-if="isUnlocked">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-14 w-14 shrink-0 rounded-2xl bg-white/50"
                    @click="openApprovalsModal"
                    title="Approvals Inbox"
                >
                    <Inbox class="h-5 w-5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-14 w-14 shrink-0 rounded-2xl bg-white/50"
                    @click="openLabelsModal"
                    title="Manage Labels"
                >
                    <Tag class="h-5 w-5" />
                </Button>
                <Button
                    class="bg-primary h-14 shrink-0 rounded-2xl px-6"
                    @click="openAddModal"
                >
                    <Plus class="mr-2 h-5 w-5" /><span class="font-bold"
                        >Add Chore</span
                    >
                </Button>
                <Button
                    variant="outline"
                    class="h-14 w-14 shrink-0 rounded-2xl bg-white/50"
                    @click="lockPortal"
                    title="Lock"
                >
                    <Lock class="h-6 w-6" />
                </Button>
            </template>
            <template v-else>
                <Button
                    variant="outline"
                    class="h-14 shrink-0 rounded-2xl bg-white/50 px-6"
                    @click="promptUnlock"
                >
                    <Unlock class="mr-2 h-5 w-5 opacity-50" /><span
                        class="font-bold"
                        >Plan</span
                    >
                </Button>
            </template>
        </div>

        <!-- Chores Board (Kanban-style columns) -->
        <div class="min-h-0 flex-1 overflow-y-auto">
            <div v-if="isLoading" class="flex h-40 items-center justify-center">
                <div
                    class="border-primary h-8 w-8 animate-spin rounded-full border-4 border-t-transparent"
                />
            </div>
            <div
                v-else-if="!groupedChores.length"
                class="text-muted-foreground flex h-40 flex-col items-center justify-center opacity-50"
            >
                <CheckCircle2 class="mb-2 h-12 w-12" />
                <p class="font-bold">No chores assigned for today!</p>
            </div>
            <div
                v-else
                class="grid items-start gap-4"
                :style="{
                    gridTemplateColumns: `repeat(${Math.min(groupedChores.length, 3)}, minmax(0, 1fr))`,
                }"
            >
                <div
                    v-for="group in groupedChores"
                    :key="group.key"
                    class="flex flex-col overflow-hidden rounded-[2rem] bg-white/40 backdrop-blur-2xl dark:bg-white/5"
                    :class="
                        group.chores.every((c) => c.completed)
                            ? group.label && group.label.is_bankable === false
                                ? 'bg-green-50/80 ring-2 ring-green-500/50 dark:bg-green-900/20'
                                : 'ring-primary/40 ring-2'
                            : ''
                    "
                >
                    <!-- Column header -->
                    <div class="flex items-start gap-3 p-5 pb-3">
                        <div
                            class="relative flex h-12 w-12 shrink-0 items-center justify-center"
                        >
                            <svg
                                class="absolute inset-0 h-12 w-12 -rotate-90"
                                viewBox="0 0 48 48"
                            >
                                <circle
                                    cx="24"
                                    cy="24"
                                    r="20"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    class="opacity-10"
                                />
                                <circle
                                    cx="24"
                                    cy="24"
                                    r="20"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    class="text-primary transition-all duration-700"
                                    stroke-dasharray="125.7"
                                    :stroke-dashoffset="
                                        125.7 -
                                        (125.7 *
                                            group.chores.filter(
                                                (c) => c.completed,
                                            ).length) /
                                            group.chores.length
                                    "
                                    stroke-linecap="round"
                                />
                            </svg>
                            <span
                                class="text-center text-[9px] leading-tight font-black tabular-nums"
                            >
                                {{
                                    group.chores.filter((c) => c.completed)
                                        .length
                                }}<br />
                                <span class="opacity-40"
                                    >/{{ group.chores.length }}</span
                                >
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Tag
                                    v-if="group.label"
                                    class="text-primary h-4 w-4 shrink-0"
                                />
                                <span
                                    class="text-base leading-tight font-black"
                                    >{{
                                        group.label
                                            ? group.label.name
                                            : 'General'
                                    }}</span
                                >
                                <span
                                    v-if="group.label?.reward"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-black text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                >
                                    <component
                                        :is="
                                            rewardIsMonetary(group.label.reward)
                                                ? DollarSign
                                                : Gift
                                        "
                                        class="h-3 w-3"
                                    />
                                    {{ formatRewardText(group.label.reward) }}
                                </span>
                            </div>
                            <div
                                class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/10 dark:bg-white/10"
                            >
                                <div
                                    class="bg-primary h-full rounded-full transition-all duration-700"
                                    :style="{
                                        width: `${(group.chores.filter((c) => c.completed).length / group.chores.length) * 100}%`,
                                    }"
                                />
                            </div>
                            <span
                                v-if="group.chores.every((c) => c.completed)"
                                class="mt-1 inline-block text-xs font-bold"
                                :class="
                                    group.label &&
                                    group.label.is_bankable === false
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-primary'
                                "
                            >
                                <template
                                    v-if="
                                        group.label &&
                                        group.label.is_bankable === false
                                    "
                                >
                                    🎉
                                    {{
                                        group.label.reward
                                            ? group.label.reward +
                                              ' earned, pending parental approval!'
                                            : 'Reward earned, pending parental approval!'
                                    }}
                                </template>
                                <template v-else> 🎉 All done! </template>
                            </span>
                        </div>
                        <!-- Clone button (Plan mode only) -->
                        <Button
                            v-if="isUnlocked"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 shrink-0 rounded-full opacity-50 hover:opacity-100"
                            title="Clone this group to another profile"
                            @click.stop="openCloneModal(group)"
                        >
                            <ClipboardCopy class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="mx-4 h-px bg-black/5 dark:bg-white/5" />

                    <!-- Chore rows -->
                    <div
                        class="custom-scrollbar flex flex-col gap-1.5 overflow-y-auto p-3"
                    >
                        <div
                            v-for="chore in group.chores"
                            :key="chore.id"
                            class="flex items-center gap-3 rounded-xl bg-white/60 px-3 py-2.5 transition-all hover:bg-white/90 dark:bg-white/10 dark:hover:bg-white/20"
                            :class="{ 'opacity-40 grayscale': chore.completed }"
                        >
                            <button
                                @click="toggleChore(chore)"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all"
                                :class="
                                    chore.completed
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border-primary/30 hover:border-primary border-2'
                                "
                            >
                                <CheckCircle2
                                    v-if="chore.completed"
                                    class="h-4 w-4"
                                />
                            </button>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <span
                                    class="truncate text-sm leading-tight font-bold"
                                    :class="{
                                        'line-through opacity-50':
                                            chore.completed,
                                    }"
                                    >{{ chore.title }}</span
                                >
                                <span
                                    v-if="chore.time"
                                    class="text-xs font-semibold opacity-40"
                                    >By {{ formatTime(chore.time) }}</span
                                >
                                <span
                                    v-if="chore.completed && chore.reward && chore.is_bankable === false"
                                    class="mt-0.5 inline-block text-xs font-bold text-green-600 dark:text-green-400"
                                >
                                    🎉 {{ chore.reward }} earned for today!
                                </span>
                            </div>
                            <div
                                v-if="chore.reward"
                                class="flex shrink-0 items-center gap-1 rounded-lg px-2 py-1"
                                :class="
                                    chore.completed
                                        ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400'
                                "
                            >
                                <component
                                    :is="
                                        rewardIsMonetary(chore.reward)
                                            ? DollarSign
                                            : Gift
                                    "
                                    class="currentColor h-3 w-3"
                                />
                                <span class="currentColor text-xs font-black">{{
                                    formatRewardText(chore.reward)
                                }}</span>
                            </div>
                            <div
                                v-if="isUnlocked"
                                class="flex shrink-0 gap-0.5"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="openDuplicateModal(chore)"
                                    class="h-7 w-7 rounded-full"
                                    title="Duplicate"
                                    ><Copy class="h-3 w-3"
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="openEditModal(chore)"
                                    class="h-7 w-7 rounded-full"
                                    title="Edit"
                                    ><Edit2 class="h-3 w-3"
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="deleteChore(chore)"
                                    class="h-7 w-7 rounded-full text-red-500 hover:bg-red-50"
                                    title="Delete"
                                    ><Trash2 class="h-3 w-3"
                                /></Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Clone Group Modal ── -->
        <Dialog v-model:open="isCloneModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ClipboardCopy class="h-5 w-5" />
                        Clone Card to Another Profile
                    </DialogTitle>
                    <DialogDescription>
                        Copies all chores in
                        <strong>{{
                            cloneTargetGroup?.label?.name ?? 'General'
                        }}</strong>
                        ({{ cloneTargetGroup?.chores?.length }} chores) to the
                        selected profile.
                    </DialogDescription>
                </DialogHeader>

                <!-- Chore preview list -->
                <div
                    class="bg-muted/40 max-h-40 overflow-y-auto rounded-xl p-3 text-sm"
                >
                    <div
                        v-for="chore in cloneTargetGroup?.chores"
                        :key="chore.id"
                        class="flex items-center gap-2 py-1"
                    >
                        <div
                            class="bg-primary/40 h-1.5 w-1.5 shrink-0 rounded-full"
                        />
                        <span class="font-medium">{{ chore.title }}</span>
                        <span
                            v-if="chore.time"
                            class="ml-auto text-xs opacity-40"
                            >{{ formatTime(chore.time) }}</span
                        >
                        <span
                            v-if="chore.reward"
                            class="text-xs font-bold text-amber-600"
                            >{{ chore.reward }}</span
                        >
                    </div>
                </div>

                <div class="grid gap-4 py-2">
                    <!-- Target profile -->
                    <div class="grid gap-1.5">
                        <label class="text-sm font-bold"
                            >Assign to Profile</label
                        >
                        <select
                            v-model="cloneToProfile"
                            class="border-input bg-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <option
                                v-for="p in profiles.filter(
                                    (p) => p.name !== activeProfile,
                                )"
                                :key="p.name"
                                :value="p.name"
                            >
                                {{ p.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Conflict mode -->
                    <div class="grid gap-1.5">
                        <label class="text-sm font-bold"
                            >If chores already exist on that profile</label
                        >
                        <div class="bg-muted flex gap-1 rounded-xl p-1">
                            <button
                                type="button"
                                @click="cloneMode = 'skip'"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-bold transition-all"
                                :class="
                                    cloneMode === 'skip'
                                        ? 'bg-background shadow'
                                        : 'text-muted-foreground'
                                "
                            >
                                Skip duplicates
                            </button>
                            <button
                                type="button"
                                @click="cloneMode = 'replace'"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-bold transition-all"
                                :class="
                                    cloneMode === 'replace'
                                        ? 'bg-background text-destructive shadow'
                                        : 'text-muted-foreground'
                                "
                            >
                                Replace all
                            </button>
                        </div>
                        <p
                            v-if="cloneMode === 'replace'"
                            class="text-destructive text-xs font-semibold"
                        >
                            ⚠️ All existing chores in this group on
                            {{ cloneToProfile }}'s board will be deleted first.
                        </p>
                    </div>

                    <!-- Result message -->
                    <div
                        v-if="cloneResult"
                        class="bg-primary/10 text-primary rounded-xl px-4 py-3 text-sm font-bold"
                    >
                        ✓ {{ cloneResult.message }}
                        <span v-if="cloneResult.skipped" class="opacity-60">
                            ({{ cloneResult.skipped }} skipped)</span
                        >
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isCloneModalOpen = false"
                        >{{ cloneResult ? 'Done' : 'Cancel' }}</Button
                    >
                    <Button
                        v-if="!cloneResult"
                        @click="executeCloneGroup"
                        :disabled="!cloneToProfile || isCloning"
                    >
                        <ClipboardCopy v-if="!isCloning" class="mr-2 h-4 w-4" />
                        <span v-if="isCloning">Cloning…</span>
                        <span v-else>Clone to {{ cloneToProfile }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── PIN Modal ── -->
        <Dialog v-model:open="isPinPromptOpen">
            <DialogContent class="sm:max-w-[360px] rounded-[2.5rem] bg-white/95 p-8 backdrop-blur-3xl dark:bg-black/95">
                <DialogHeader>
                    <DialogTitle class="text-center text-2xl font-black uppercase">Parental Passcode</DialogTitle>
                    <DialogDescription class="text-center font-bold">
                        Enter the passcode to manage chores.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <!-- PIN Dots Display -->
                    <div class="mb-8 flex justify-center gap-4">
                        <div 
                            v-for="i in 4" 
                            :key="i"
                            class="h-5 w-5 rounded-full transition-all duration-200"
                            :class="enteredPin.length >= i ? 'bg-primary scale-110' : 'bg-primary/20 dark:bg-white/10 scale-100'"
                        ></div>
                    </div>
                    <p v-if="pinError" class="mb-4 text-center text-sm font-black uppercase tracking-widest text-red-500 animate-pulse">
                        Incorrect PIN
                    </p>

                    <!-- Numeric Keypad -->
                    <div class="grid grid-cols-3 gap-3">
                        <Button 
                            v-for="num in [1, 2, 3, 4, 5, 6, 7, 8, 9]" 
                            :key="num"
                            variant="outline"
                            class="h-16 rounded-2xl text-2xl font-black border-2 hover:bg-black/5 dark:hover:bg-white/5"
                            @click="appendPin(num.toString())"
                        >
                            {{ num }}
                        </Button>
                        <Button 
                            variant="ghost"
                            class="h-16 rounded-2xl text-sm font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 hover:text-red-600"
                            @click="enteredPin = ''"
                        >
                            Clear
                        </Button>
                        <Button 
                            variant="outline"
                            class="h-16 rounded-2xl text-2xl font-black border-2 hover:bg-black/5 dark:hover:bg-white/5"
                            @click="appendPin('0')"
                        >
                            0
                        </Button>
                        <Button 
                            variant="ghost"
                            class="h-16 rounded-2xl text-sm font-black uppercase tracking-widest hover:bg-black/5 dark:hover:bg-white/5"
                            @click="enteredPin = enteredPin.slice(0, -1)"
                        >
                            <Delete class="h-6 w-6" />
                        </Button>
                    </div>
                </div>
                <DialogFooter class="sm:justify-center">
                    <Button variant="outline" class="w-full rounded-2xl h-12 font-black uppercase tracking-widest" @click="isPinPromptOpen = false">
                        Cancel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Add/Edit Chore Modal ── -->
        <Dialog v-model:open="isManageModalOpen">
            <DialogContent class="sm:max-w-[540px]">
                <DialogHeader>
                    <DialogTitle>{{
                        editingChore ? 'Edit Chore' : 'Add New Chore'
                    }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-5 py-4">
                    <!-- Title -->
                    <div class="grid gap-1.5">
                        <label class="text-sm font-bold">Chore Title</label>
                        <Input
                            v-model="newChore.title"
                            placeholder="e.g. Empty Dishwasher"
                        />
                    </div>

                    <!-- Assign To -->
                    <div class="grid gap-1.5">
                        <label class="text-sm font-bold">Assign To</label>
                        <select
                            v-model="newChore.profile"
                            class="border-input bg-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <option
                                v-for="p in profiles"
                                :key="p.name"
                                :value="p.name"
                            >
                                {{ p.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Label -->
                    <div class="grid gap-1.5">
                        <label
                            class="flex items-center gap-1.5 text-sm font-bold"
                            ><Tag class="h-3.5 w-3.5" /> Label
                            <span class="font-normal opacity-50"
                                >(Optional)</span
                            ></label
                        >
                        <select
                            v-model="newChore.label_id"
                            class="border-input bg-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <option :value="null">— No Label —</option>
                            <option
                                v-for="l in labels"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.name }}
                                {{
                                    l.reward
                                        ? (rewardIsMonetary(l.reward)
                                              ? ' 💲'
                                              : ' 🎁') +
                                          ' ' +
                                          formatRewardText(l.reward)
                                        : ''
                                }}
                                {{ l.bonus_reward ? ' ⭐ Streak' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Label Reward Info Box -->
                    <div
                        v-if="
                            selectedLabel &&
                            (selectedLabel.reward || selectedLabel.bonus_reward)
                        "
                        class="space-y-3 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-amber-700 dark:text-amber-400"
                        >
                            <Gift
                                class="h-4 w-4 text-amber-600 dark:text-amber-500"
                            />
                            Label Reward Attached
                        </div>
                        <div class="space-y-1.5 text-sm">
                            <p
                                v-if="selectedLabel.reward"
                                class="text-foreground flex items-center gap-1.5 text-sm font-medium"
                            >
                                <span
                                    class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                                    >Base Reward:</span
                                >
                                <component
                                    :is="
                                        rewardIsMonetary(selectedLabel.reward)
                                            ? DollarSign
                                            : Gift
                                    "
                                    class="h-3.5 w-3.5 text-amber-600 dark:text-amber-500"
                                />
                                {{ formatRewardText(selectedLabel.reward) }}
                                <span
                                    v-if="selectedLabel.is_bankable"
                                    class="text-muted-foreground text-xs font-normal"
                                >
                                    (Bankable)
                                </span>
                            </p>

                            <div
                                v-if="selectedLabel.bonus_reward"
                                class="mt-2 space-y-1 border-t border-amber-500/15 pt-2"
                            >
                                <p
                                    class="text-foreground text-xs font-bold tracking-wider uppercase"
                                >
                                    Streak / Bonus Reward
                                </p>
                                <p class="text-foreground text-sm font-medium">
                                    {{
                                        selectedLabel.bonus_reward.reward_value
                                    }}
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    Earned by completing on:
                                    <span class="text-foreground font-bold">
                                        {{
                                            formatRequiredDays(
                                                selectedLabel.bonus_reward
                                                    .required_days,
                                            )
                                        }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Reward -->
                    <template v-if="!selectedLabelHasReward">
                        <div class="border-t pt-4">
                            <div class="flex items-center justify-between">
                                <button
                                    type="button"
                                    @click="
                                        isRewardExpanded = !isRewardExpanded
                                    "
                                    class="flex flex-1 cursor-pointer items-center justify-between py-2 text-left focus:outline-none"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm font-bold"
                                    >
                                        <Gift class="text-primary h-4 w-4" />
                                        Custom Reward
                                        <span
                                            class="text-muted-foreground text-xs font-normal"
                                        >
                                            (Optional — overrides label reward)
                                        </span>
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 shrink-0 transition-transform duration-200"
                                        :class="{
                                            'rotate-180': isRewardExpanded,
                                        }"
                                    />
                                </button>
                                <!-- Clear Reward Button -->
                                <Button
                                    v-if="monetaryAmount || textRewardValue"
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    class="h-7 px-2 text-xs font-bold text-red-500 hover:bg-red-50"
                                    @click.stop="clearReward"
                                >
                                    Clear
                                </Button>
                            </div>

                            <div
                                v-show="isRewardExpanded"
                                class="mt-3 space-y-4"
                            >
                                <!-- Type Switcher -->
                                <div
                                    class="bg-muted flex w-fit gap-1 rounded-xl p-1"
                                >
                                    <button
                                        type="button"
                                        @click="
                                            ((rewardType = 'monetary'),
                                            (textRewardValue = ''))
                                        "
                                        class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-bold transition-all"
                                        :class="
                                            rewardType === 'monetary'
                                                ? 'bg-background text-foreground shadow'
                                                : 'text-muted-foreground hover:text-foreground'
                                        "
                                    >
                                        <DollarSign class="h-4 w-4" /> Monetary
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            ((rewardType = 'text'),
                                            (monetaryAmount = ''))
                                        "
                                        class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-bold transition-all"
                                        :class="
                                            rewardType === 'text'
                                                ? 'bg-background text-foreground shadow'
                                                : 'text-muted-foreground hover:text-foreground'
                                        "
                                    >
                                        <Gift class="h-4 w-4" /> Other
                                    </button>
                                </div>

                                <!-- Monetary input -->
                                <div
                                    v-if="rewardType === 'monetary'"
                                    class="flex flex-col gap-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <span
                                                class="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2 font-bold"
                                                >$</span
                                            >
                                            <Input
                                                v-model="monetaryAmount"
                                                type="number"
                                                min="0"
                                                step="0.25"
                                                placeholder="0.00"
                                                class="pl-7"
                                            />
                                        </div>
                                        <span
                                            class="text-muted-foreground text-sm"
                                            >per chore</span
                                        >
                                    </div>
                                    <!-- Monetary rewards are always bankable -->
                                </div>

                                <!-- Text reward input + library -->
                                <div v-else class="flex flex-col gap-2">
                                    <div class="flex gap-2">
                                        <Input
                                            v-model="textRewardValue"
                                            placeholder="e.g. 30 min screen time"
                                            class="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="shrink-0 px-3"
                                            @click="
                                                showRewardLibraryPicker =
                                                    !showRewardLibraryPicker
                                            "
                                            title="Pick from library"
                                        >
                                            <Tag class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="shrink-0 px-3 text-xs"
                                            :disabled="
                                                !textRewardValue.trim() ||
                                                textRewardLibrary.includes(
                                                    textRewardValue.trim(),
                                                )
                                            "
                                            @click="saveTextRewardToLibrary"
                                            title="Save to library"
                                        >
                                            <Check class="mr-1 h-4 w-4" /> Save
                                        </Button>
                                    </div>

                                    <label
                                        class="mt-1 flex cursor-pointer items-center justify-between py-1.5"
                                        v-if="textRewardValue"
                                    >
                                        <span class="text-sm font-bold"
                                            >Bankable (Rolls over to next
                                            day)</span
                                        >
                                        <Switch
                                            v-model:checked="
                                                newChore.is_bankable
                                            "
                                        />
                                    </label>

                                    <!-- Library picker -->
                                    <div
                                        v-if="showRewardLibraryPicker"
                                        class="border-border bg-background rounded-xl border shadow-lg"
                                    >
                                        <div
                                            class="max-h-40 overflow-y-auto p-2"
                                        >
                                            <button
                                                v-for="item in textRewardLibrary"
                                                :key="item"
                                                type="button"
                                                @click="pickLibraryReward(item)"
                                                class="hover:bg-muted group flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors"
                                            >
                                                <span>{{ item }}</span>
                                                <X
                                                    class="h-3.5 w-3.5 text-red-500 opacity-0 group-hover:opacity-50"
                                                    @click.stop="
                                                        removeLibraryReward(
                                                            item,
                                                        )
                                                    "
                                                />
                                            </button>
                                            <p
                                                v-if="!textRewardLibrary.length"
                                                class="text-muted-foreground px-3 py-4 text-center text-xs"
                                            >
                                                Library is empty.
                                            </p>
                                        </div>
                                        <div class="flex gap-2 border-t p-2">
                                            <Input
                                                v-model="newLibraryRewardInput"
                                                placeholder="Add to library…"
                                                class="h-8 text-xs"
                                                @keyup.enter="addLibraryReward"
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                class="h-8 px-2"
                                                @click="addLibraryReward"
                                                ><Plus class="h-4 w-4"
                                            /></Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chore Streak / Bonus Reward Section -->
                                <div
                                    v-show="
                                        newChore.is_bankable &&
                                        (monetaryAmount || textRewardValue)
                                    "
                                    class="border-primary/20 bg-primary/5 rounded-xl border p-4"
                                >
                                    <label
                                        class="mb-3 flex cursor-pointer items-center justify-between py-1"
                                    >
                                        <span
                                            class="text-primary text-sm font-bold"
                                            >Enable Streak / Bonus Reward</span
                                        >
                                        <Switch
                                            v-model:checked="choreHasBonus"
                                        />
                                    </label>

                                    <div
                                        v-if="choreHasBonus"
                                        class="border-primary/20 grid gap-4 border-l-2 pl-6 transition-all"
                                    >
                                        <div>
                                            <label
                                                class="text-muted-foreground mb-1.5 block text-xs font-bold tracking-wider uppercase"
                                                >Required Days (Current
                                                Week)</label
                                            >
                                            <div class="flex flex-wrap gap-1">
                                                <button
                                                    v-for="(day, index) in [
                                                        'Sun',
                                                        'Mon',
                                                        'Tue',
                                                        'Wed',
                                                        'Thu',
                                                        'Fri',
                                                        'Sat',
                                                    ]"
                                                    :key="index"
                                                    type="button"
                                                    @click="
                                                        toggleChoreBonusDay(
                                                            index,
                                                        )
                                                    "
                                                    class="h-8 w-8 rounded-full text-xs font-bold transition-all"
                                                    :class="
                                                        choreBonusDays.includes(
                                                            index,
                                                        )
                                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                    "
                                                >
                                                    {{ day.charAt(0) }}
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <label
                                                class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                                >Bonus Reward</label
                                            >
                                            <Input
                                                v-model="choreBonusValue"
                                                placeholder="e.g. 15 mins stay up late or $5.00"
                                            />
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label
                                                    class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                                    >Expires In (Days)</label
                                                >
                                                <Input
                                                    v-model="choreBonusExpires"
                                                    type="number"
                                                    min="1"
                                                    placeholder="Never"
                                                />
                                            </div>
                                            <div class="flex items-center pt-5">
                                                <label
                                                    class="flex flex-1 cursor-pointer items-center justify-between gap-2"
                                                >
                                                    <span
                                                        class="text-xs leading-tight font-bold"
                                                        >Require Approval</span
                                                    >
                                                    <Switch
                                                        v-model:checked="
                                                            choreBonusRequiresApproval
                                                        "
                                                    />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Time Selector -->
                    <div class="grid gap-1.5">
                        <label
                            class="flex items-center justify-between text-sm font-bold"
                        >
                            <span>
                                Must be completed by
                                <span class="font-normal opacity-50"
                                    >(Optional)</span
                                >
                            </span>
                            <button
                                v-if="choreTimeHour !== null"
                                type="button"
                                @click="choreTimeHour = null"
                                class="text-xs font-bold text-red-500 transition-colors hover:text-red-600"
                            >
                                Clear Time
                            </button>
                        </label>

                        <div v-if="choreTimeHour === null">
                            <Button
                                type="button"
                                variant="outline"
                                class="hover:bg-muted border-muted-foreground/30 text-muted-foreground flex h-11 w-full items-center justify-center gap-2 rounded-2xl border-dashed font-bold"
                                @click="enableTimeLimit"
                            >
                                <Clock class="h-4 w-4" /> Add Completion Time
                            </Button>
                        </div>
                        <div
                            v-else
                            class="bg-card border-border flex h-12 divide-x overflow-hidden rounded-2xl border shadow-sm"
                        >
                            <!-- Hours Column -->
                            <div
                                ref="hoursScrollContainer"
                                @scroll="onHoursScroll"
                                class="flex-1 snap-y snap-mandatory overflow-y-auto scroll-smooth py-0 text-center"
                                style="
                                    scrollbar-width: none;
                                    -ms-overflow-style: none;
                                "
                            >
                                <button
                                    v-for="h in 12"
                                    :key="h"
                                    type="button"
                                    @click="selectHour(h)"
                                    class="block flex h-12 w-full shrink-0 snap-center items-center justify-center text-sm font-bold transition-colors"
                                    :class="
                                        choreTimeHour === h
                                            ? 'bg-primary text-primary-foreground font-black'
                                            : 'hover:bg-muted text-foreground/80'
                                    "
                                >
                                    {{ h }}
                                </button>
                            </div>
                            <!-- Minutes Column -->
                            <div
                                ref="minutesScrollContainer"
                                @scroll="onMinutesScroll"
                                class="flex-1 snap-y snap-mandatory overflow-y-auto scroll-smooth py-0 text-center"
                                style="
                                    scrollbar-width: none;
                                    -ms-overflow-style: none;
                                "
                            >
                                <button
                                    v-for="m in computedMinutesList"
                                    :key="m"
                                    type="button"
                                    @click="selectMinute(m)"
                                    class="block flex h-12 w-full shrink-0 snap-center items-center justify-center text-sm font-bold transition-colors"
                                    :class="
                                        choreTimeMinute === m
                                            ? 'bg-primary text-primary-foreground font-black'
                                            : 'hover:bg-muted text-foreground/80'
                                    "
                                >
                                    {{ m }}
                                </button>
                            </div>
                            <!-- AM/PM Column -->
                            <div
                                ref="periodScrollContainer"
                                @scroll="onPeriodScroll"
                                class="w-20 snap-y snap-mandatory overflow-y-auto scroll-smooth py-0 text-center"
                                style="
                                    scrollbar-width: none;
                                    -ms-overflow-style: none;
                                "
                            >
                                <button
                                    v-for="p in ['AM', 'PM']"
                                    :key="p"
                                    type="button"
                                    @click="selectPeriod(p)"
                                    class="block flex h-12 w-full shrink-0 snap-center items-center justify-center text-xs font-black transition-colors"
                                    :class="
                                        choreTimePeriod === p
                                            ? 'bg-primary text-primary-foreground font-black'
                                            : 'hover:bg-muted text-foreground/80'
                                    "
                                >
                                    {{ p }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Days -->
                    <div class="grid gap-1.5">
                        <label class="text-sm font-bold">Repeats On</label>
                        <div class="flex gap-1.5">
                            <button
                                v-for="day in daysOfWeek"
                                :key="day.value"
                                type="button"
                                @click="toggleDay(day.value)"
                                class="flex h-10 w-10 flex-1 items-center justify-center rounded-full text-sm font-bold transition-all"
                                :class="
                                    newChore.days.includes(day.value)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ day.label }}
                            </button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isManageModalOpen = false"
                        >Cancel</Button
                    >
                    <Button @click="saveChore" :disabled="!newChore.title"
                        >Save Chore</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Labels Library Modal ── -->
        <Dialog v-model:open="isLabelsModalOpen">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"
                        ><Tag class="h-5 w-5" /> Label Library</DialogTitle
                    >
                    <DialogDescription
                        >Reusable labels you can attach to chores. A label can
                        carry a group reward.</DialogDescription
                    >
                </DialogHeader>
                <div class="max-h-[40vh] space-y-2 overflow-y-auto py-2">
                    <div
                        v-if="!labels.length"
                        class="text-muted-foreground py-8 text-center text-sm"
                    >
                        No labels yet.
                    </div>
                    <div
                        v-for="label in labels"
                        :key="label.id"
                        class="bg-muted/40 rounded-xl p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold">
                                    {{ label.name }}
                                </div>
                                <div
                                    v-if="label.reward"
                                    class="mt-0.5 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    <component
                                        :is="
                                            rewardIsMonetary(label.reward)
                                                ? DollarSign
                                                : Gift
                                        "
                                        class="h-3 w-3"
                                    />
                                    {{ formatRewardText(label.reward) }}
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 rounded-full"
                                    @click="startEditLabel(label)"
                                    ><Edit2 class="h-3.5 w-3.5"
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 rounded-full text-red-500 hover:bg-red-50"
                                    @click="deleteLabel(label)"
                                    ><Trash2 class="h-3.5 w-3.5"
                                /></Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <Button @click="startCreateLabel" class="w-full font-bold">
                        <Plus class="mr-2 h-4 w-4" /> Create New Label
                    </Button>
                </div>

                <DialogFooter class="mt-2">
                    <Button variant="outline" @click="isLabelsModalOpen = false"
                        >Done</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Create / Edit Label Form Modal ── -->
        <Dialog v-model:open="isLabelFormOpen">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Tag class="h-5 w-5" />
                        {{ editingLabel ? 'Edit Label' : 'Create Label' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editingLabel
                                ? 'Update the details for this label.'
                                : 'Create a new reusable label for chores.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] space-y-4 overflow-y-auto py-2 pr-1">
                    <div class="space-y-2">
                        <label class="text-sm font-bold">Label Name</label>
                        <Input
                            v-model="newLabelName"
                            placeholder="Label name"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold">Reward Type</label>
                        <div class="bg-muted flex w-fit gap-1 rounded-xl p-1">
                            <button
                                type="button"
                                @click="
                                    ((labelRewardType = 'monetary'),
                                    (labelTextReward = ''))
                                "
                                class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    labelRewardType === 'monetary'
                                        ? 'bg-background shadow'
                                        : 'text-muted-foreground'
                                "
                            >
                                <DollarSign class="h-3.5 w-3.5" /> Money
                            </button>
                            <button
                                type="button"
                                @click="
                                    ((labelRewardType = 'text'),
                                    (labelMonetaryAmount = ''))
                                "
                                class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    labelRewardType === 'text'
                                        ? 'bg-background shadow'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Gift class="h-3.5 w-3.5" /> Other
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold">Reward Value</label>
                        <div
                            v-if="labelRewardType === 'monetary'"
                            class="relative"
                        >
                            <span
                                class="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2 font-bold"
                                >$</span
                            >
                            <Input
                                v-model="labelMonetaryAmount"
                                type="number"
                                min="0"
                                step="0.25"
                                placeholder="0.00"
                                class="pl-7"
                            />
                        </div>
                        <Input
                            v-else
                            v-model="labelTextReward"
                            placeholder="e.g. Movie night"
                        />
                    </div>

                    <label
                        class="mt-1 flex cursor-pointer items-center justify-between py-1.5"
                        v-if="labelRewardType !== 'monetary'"
                    >
                        <span class="text-sm font-bold"
                            >Bankable (Rolls over to next day)</span
                        >
                        <Switch v-model:checked="newLabelIsBankable" />
                    </label>

                    <div
                        v-show="newLabelIsBankable"
                        class="border-primary/20 bg-primary/5 rounded-xl border p-4"
                    >
                        <label
                            class="mb-3 flex cursor-pointer items-center justify-between py-1"
                        >
                            <span class="text-primary text-sm font-bold"
                                >Enable Streak / Bonus Reward</span
                            >
                            <Switch v-model:checked="labelHasBonus" />
                        </label>

                        <div
                            v-if="labelHasBonus"
                            class="border-primary/20 grid gap-4 border-l-2 pl-6 transition-all"
                        >
                            <div>
                                <label
                                    class="text-muted-foreground mb-1.5 block text-xs font-bold tracking-wider uppercase"
                                    >Required Days (Current Week)</label
                                >
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="(day, index) in [
                                            'Sun',
                                            'Mon',
                                            'Tue',
                                            'Wed',
                                            'Thu',
                                            'Fri',
                                            'Sat',
                                        ]"
                                        :key="index"
                                        type="button"
                                        @click="toggleLabelBonusDay(index)"
                                        class="h-8 w-8 rounded-full text-xs font-bold transition-all"
                                        :class="
                                            labelBonusDays.includes(index)
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                        "
                                    >
                                        {{ day.charAt(0) }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                    >Bonus Reward</label
                                >
                                <Input
                                    v-model="labelBonusValue"
                                    placeholder="e.g. 15 mins stay up late or $5.00"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                        >Expires In (Days)</label
                                    >
                                    <Input
                                        v-model="labelBonusExpires"
                                        type="number"
                                        min="1"
                                        placeholder="Never"
                                    />
                                </div>
                                <div class="flex items-center pt-5">
                                    <label
                                        class="flex flex-1 cursor-pointer items-center justify-between gap-2"
                                    >
                                        <span
                                            class="text-xs leading-tight font-bold"
                                            >Require Approval</span
                                        >
                                        <Switch
                                            v-model:checked="
                                                labelBonusRequiresApproval
                                            "
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" @click="cancelEditLabel"
                        >Cancel</Button
                    >
                    <Button @click="saveLabel" :disabled="!newLabelName.trim()"
                        >Save</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bank Modal -->
        <Dialog v-model:open="isBankModalOpen">
            <DialogContent class="max-w-md rounded-3xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-amber-500" />
                        {{ activeProfile }}'s Bank
                    </DialogTitle>
                </DialogHeader>
                <div class="space-y-6 py-4">
                    <div
                        class="rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 p-6 text-center shadow-inner dark:from-amber-900/30 dark:to-orange-900/30"
                    >
                        <p
                            class="mb-1 text-sm font-bold tracking-widest text-amber-600 uppercase dark:text-amber-400"
                        >
                            Balance
                        </p>
                        <h2
                            class="text-5xl font-black text-amber-600 dark:text-amber-400"
                        >
                            ${{ parseFloat(bankBalance).toFixed(2) }}
                        </h2>
                        <Button
                            v-if="parseFloat(bankBalance) > 0"
                            size="sm"
                            variant="outline"
                            class="mt-4 bg-white/50 dark:bg-black/50"
                            @click="confirmRedeemReward('monetary', bankBalance)"
                            >Redeem All</Button
                        >
                        <p
                            v-if="parseFloat(pendingBankBalance) > 0"
                            class="mt-3 text-xs font-bold text-amber-700/60 dark:text-amber-300/60"
                        >
                            + ${{ parseFloat(pendingBankBalance).toFixed(2) }}
                            Pending Approval
                        </p>
                    </div>

                    <div
                        v-if="
                            bankTextRewards.length ||
                            pendingBankTextRewards.length ||
                            dailyBankTextRewards.length
                        "
                        class="space-y-2"
                    >
                        <p
                            v-if="dailyBankTextRewards.length"
                            class="text-muted-foreground text-sm font-bold tracking-widest uppercase mt-4"
                        >
                            Daily Perks (Expires Today)
                        </p>
                        <div
                            v-for="reward in dailyBankTextRewards"
                            :key="'daily_' + reward.text"
                            class="bg-green-100/50 flex items-center justify-between rounded-xl p-3 dark:bg-green-900/20"
                        >
                            <div class="flex items-center gap-2 font-semibold text-green-700 dark:text-green-400">
                                <Gift class="h-4 w-4" />
                                <span>{{ reward.count }}x</span>
                                <span>{{ reward.text }}</span>
                            </div>
                            <Button
                                size="sm"
                                variant="secondary"
                                class="bg-green-200 hover:bg-green-300 text-green-800 dark:bg-green-800 dark:hover:bg-green-700 dark:text-green-100"
                                @click="confirmRedeemReward('textual', 1, reward.text, true)"
                                >Redeem</Button
                            >
                        </div>

                        <p
                            v-if="bankTextRewards.length || pendingBankTextRewards.length"
                            class="text-muted-foreground text-sm font-bold tracking-widest uppercase mt-4"
                        >
                            Saved Rewards
                        </p>
                        <div
                            v-for="reward in bankTextRewards"
                            :key="reward.text"
                            class="bg-muted/50 flex items-center justify-between rounded-xl p-3"
                        >
                            <div class="flex items-center gap-2 font-semibold">
                                <Gift class="h-4 w-4 text-purple-500" />
                                <span>{{ reward.count }}x</span>
                                <span>{{ reward.text }}</span>
                            </div>
                            <Button
                                size="sm"
                                variant="secondary"
                                @click="confirmRedeemReward('textual', 1, reward.text)"
                                >Redeem</Button
                            >
                        </div>
                        <div
                            v-for="reward in pendingBankTextRewards"
                            :key="'pending_' + reward.text"
                            class="bg-muted/30 flex items-center justify-between rounded-xl p-3 opacity-60"
                        >
                            <div
                                class="text-muted-foreground flex items-center gap-2 font-semibold"
                            >
                                <Gift class="h-4 w-4 text-purple-500/50" />
                                <span>{{ reward.count }}x</span>
                                <span>{{ reward.text }}</span>
                            </div>
                            <span
                                class="text-muted-foreground text-xs font-bold"
                                >Pending Approval</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="
                            !bankTextRewards.length &&
                            bankBalance <= 0 &&
                            !pendingBankTextRewards.length &&
                            pendingBankBalance <= 0 &&
                            !dailyBankTextRewards.length
                        "
                        class="text-muted-foreground py-4 text-center opacity-50"
                    >
                        <p class="font-bold">No rewards earned yet.</p>
                        <p class="text-sm">Complete chores to earn rewards!</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isBankModalOpen = false"
                        >Close</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Approvals Inbox Modal -->
        <Dialog v-model:open="isApprovalsModalOpen">
            <DialogContent
                class="flex max-h-[80vh] max-w-lg flex-col overflow-hidden rounded-3xl"
            >
                <DialogHeader class="shrink-0">
                    <DialogTitle class="flex items-center gap-2">
                        <Inbox class="h-5 w-5" /> Pending Approvals
                    </DialogTitle>
                    <DialogDescription
                        >Review completed chores and award
                        rewards.</DialogDescription
                    >
                </DialogHeader>

                <div class="flex-1 space-y-3 overflow-y-auto py-4 pr-2">
                    <div
                        v-if="!pendingApprovals.length"
                        class="text-muted-foreground py-8 text-center opacity-50"
                    >
                        <CheckCircle2 class="mx-auto mb-2 h-12 w-12" />
                        <p class="font-bold">All caught up!</p>
                        <p class="text-sm">No pending chores to approve.</p>
                    </div>
                    <div
                        v-for="completion in pendingApprovals"
                        :key="completion.id"
                        class="bg-muted/40 flex flex-col gap-3 rounded-2xl border p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="text-lg font-bold">
                                    {{ completion.chore.title }}
                                </h4>
                                <p
                                    class="text-muted-foreground flex items-center gap-2 text-sm font-semibold"
                                >
                                    <span
                                        >Profile:
                                        {{ completion.chore.profile }}</span
                                    >
                                    &bull;
                                    <span>Date: {{ completion.date }}</span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-1.5"
                            v-if="
                                completion.awarded_value !== null &&
                                completion.awarded_value !== ''
                            "
                        >
                            <label
                                class="text-muted-foreground text-xs font-bold uppercase"
                                >Reward Value</label
                            >
                            <Input
                                v-model="completion.awarded_value"
                                class="bg-background font-bold"
                            />
                        </div>
                        <div
                            v-else
                            class="text-muted-foreground bg-background/50 rounded-lg border p-2 text-center text-sm italic"
                        >
                            No reward attached to this chore.
                        </div>

                        <div class="mt-2 flex justify-end gap-2">
                            <Button
                                variant="outline"
                                class="text-red-500 hover:bg-red-50 hover:text-red-600"
                                @click="processApproval(completion, 'reject')"
                            >
                                <X class="mr-1 h-4 w-4" /> Reject
                            </Button>
                            <Button
                                class="bg-green-500 text-white shadow-md hover:bg-green-600"
                                @click="processApproval(completion, 'approve')"
                            >
                                <Check class="mr-1 h-4 w-4" /> Approve
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-2 shrink-0">
                    <Button
                        variant="outline"
                        @click="isApprovalsModalOpen = false"
                        >Close</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Redeem Confirmation Modal -->
        <Dialog v-model:open="isRedeemModalOpen">
            <DialogContent class="sm:max-w-[400px] rounded-3xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Gift class="h-5 w-5 text-purple-500" v-if="redeemPayload?.type === 'textual'" />
                        <DollarSign class="h-5 w-5 text-amber-500" v-else />
                        Redeem Reward
                    </DialogTitle>
                    <DialogDescription class="sr-only">Confirm redemption of the selected reward.</DialogDescription>
                </DialogHeader>
                <div class="py-6 text-center space-y-4">
                    <p class="text-lg font-medium text-muted-foreground">
                        Are you sure you want to redeem:
                    </p>
                    <div class="bg-muted/50 rounded-2xl p-4 inline-block mx-auto min-w-[200px]">
                        <p class="text-2xl font-black" :class="redeemPayload?.type === 'monetary' ? 'text-amber-600 dark:text-amber-400' : 'text-purple-600 dark:text-purple-400'">
                            {{ redeemPayload?.type === 'monetary' ? '$' + parseFloat(redeemPayload.amount).toFixed(2) : redeemPayload?.text }}
                        </p>
                    </div>
                    <p class="text-sm font-semibold text-muted-foreground">
                        This action cannot be undone.
                    </p>
                </div>
                <DialogFooter class="flex gap-2">
                    <Button variant="outline" class="flex-1" @click="isRedeemModalOpen = false">Cancel</Button>
                    <Button class="flex-1" :class="redeemPayload?.type === 'monetary' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-purple-500 hover:bg-purple-600 text-white'" @click="executeRedeemReward">
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
