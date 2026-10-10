<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { RefreshCw, LogOut } from 'lucide-vue-next'
import {
    getToken,
    getStoredDevice,
    fetchBootstrap,
    unpair,
    UNAUTHORIZED_EVENT,
} from '@/lib/mobileApi'
import PairingView from './PairingView.vue'
import ChoresView from './ChoresView.vue'
import LabelsView from './LabelsView.vue'
import ApprovalsView from './ApprovalsView.vue'
import RewardsView from './RewardsView.vue'

const isPaired = ref(!!getToken())
const bootstrapping = ref(false)
const profiles = ref([])
const labels = ref([])
const device = ref(getStoredDevice())
const activeProfile = ref('')
const activeTab = ref('chores')
const toast = ref('')

const tabs = [
    { id: 'chores', label: 'Chores' },
    { id: 'labels', label: 'Labels' },
    { id: 'approvals', label: 'Approvals' },
    { id: 'rewards', label: 'Rewards' },
]

let toastTimer = null
function showToast(message) {
    toast.value = message
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => (toast.value = ''), 2600)
}

async function loadBootstrap() {
    bootstrapping.value = true
    try {
        const data = await fetchBootstrap()
        profiles.value = data.profiles || []
        labels.value = data.labels || []
        device.value = data.device || device.value
        if (!activeProfile.value || !profiles.value.includes(activeProfile.value)) {
            activeProfile.value = profiles.value[0] || ''
        }
    } catch {
        /* 401 handled by interceptor */
    } finally {
        bootstrapping.value = false
    }
}

function onPaired(payload) {
    device.value = payload.device
    isPaired.value = true
    loadBootstrap()
}

async function signOut() {
    await unpair()
    isPaired.value = false
    device.value = null
    showToast('Unpaired from this hub.')
}

function onUnauthorized() {
    isPaired.value = false
    device.value = null
    showToast('Session expired. Please pair again.')
}

onMounted(() => {
    window.addEventListener(UNAUTHORIZED_EVENT, onUnauthorized)
    if (isPaired.value) loadBootstrap()
})

onBeforeUnmount(() => {
    window.removeEventListener(UNAUTHORIZED_EVENT, onUnauthorized)
    clearTimeout(toastTimer)
})
</script>

<template>
    <PairingView v-if="!isPaired" @paired="onPaired" />

    <div v-else class="flex min-h-dvh flex-col bg-slate-950 text-slate-100">
        <header
            class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/80 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 backdrop-blur-xl"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-black tracking-tight">Family Hub</h1>
                    <p class="text-[11px] font-medium text-slate-400">
                        {{ device?.name || 'Paired device' }}
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        class="grid h-9 w-9 place-items-center rounded-full text-slate-300 active:bg-white/10"
                        :class="{ 'animate-spin': bootstrapping }"
                        aria-label="Refresh"
                        @click="loadBootstrap"
                    >
                        <RefreshCw class="h-4 w-4" />
                    </button>
                    <button
                        class="grid h-9 w-9 place-items-center rounded-full text-slate-300 active:bg-white/10"
                        aria-label="Unpair"
                        @click="signOut"
                    >
                        <LogOut class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div
                v-if="profiles.length"
                class="mt-3 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]"
            >
                <button
                    v-for="profile in profiles"
                    :key="profile"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-bold transition"
                    :class="
                        activeProfile === profile
                            ? 'bg-sky-500 text-slate-950'
                            : 'bg-white/5 text-slate-300'
                    "
                    @click="activeProfile = profile"
                >
                    {{ profile }}
                </button>
            </div>
        </header>

        <main class="flex-1 px-4 py-4 pb-28">
            <ChoresView
                v-if="activeTab === 'chores'"
                :active-profile="activeProfile"
                :profiles="profiles"
                :labels="labels"
                @toast="showToast"
            />
            <LabelsView
                v-else-if="activeTab === 'labels'"
                :labels="labels"
                @changed="loadBootstrap"
                @toast="showToast"
            />
            <ApprovalsView
                v-else-if="activeTab === 'approvals'"
                @toast="showToast"
            />
            <RewardsView
                v-else-if="activeTab === 'rewards'"
                :active-profile="activeProfile"
                @toast="showToast"
            />
        </main>

        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-white/10 bg-slate-950/90 pb-[max(0.5rem,env(safe-area-inset-bottom))] backdrop-blur-xl"
        >
            <div class="grid grid-cols-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="flex flex-col items-center gap-1 py-3 text-[11px] font-bold uppercase tracking-wide transition"
                    :class="
                        activeTab === tab.id ? 'text-sky-400' : 'text-slate-500'
                    "
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>
        </nav>

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 translate-y-2"
            leave-active-class="transition duration-200"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="toast"
                class="fixed inset-x-0 bottom-24 z-40 mx-auto w-fit max-w-[90%] rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg"
            >
                {{ toast }}
            </div>
        </Transition>
    </div>
</template>
