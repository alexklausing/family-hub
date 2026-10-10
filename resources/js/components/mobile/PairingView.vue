<script setup>
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { pair, setToken, setStoredDevice } from '@/lib/mobileApi'

const emit = defineEmits(['paired'])

const code = ref('')
const deviceName = ref('iPhone')
const isSubmitting = ref(false)
const error = ref('')

async function submit() {
    error.value = ''
    const trimmed = code.value.trim()
    if (!trimmed) {
        error.value = 'Enter the pairing code from your hub.'
        return
    }

    isSubmitting.value = true
    try {
        const data = await pair(trimmed, deviceName.value.trim() || 'iPhone')
        setToken(data.token)
        setStoredDevice(data.device)
        emit('paired', data)
    } catch (e) {
        const status = e?.response?.status
        if (status === 503) {
            error.value = 'Pairing is not configured on the hub yet.'
        } else if (status === 429) {
            error.value = 'Too many attempts. Wait a minute and try again.'
        } else if (status === 422) {
            error.value =
                e.response?.data?.message || 'That pairing code is incorrect.'
        } else {
            error.value =
                'Could not reach the hub. Make sure you are on the home network.'
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div
        class="flex min-h-dvh flex-col justify-center bg-slate-950 px-6 py-12 text-slate-100"
    >
        <div class="mx-auto w-full max-w-sm">
            <div
                class="mb-8 grid h-16 w-16 place-items-center rounded-3xl bg-sky-500/15 text-3xl"
            >
                🏠
            </div>
            <h1 class="text-3xl font-black tracking-tight">Pair with your hub</h1>
            <p class="mt-2 text-sm text-slate-400">
                Enter the pairing code configured on your Family Hub to manage
                chores from this phone.
            </p>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <div>
                    <label
                        class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-slate-400"
                        for="pairing-code"
                    >
                        Pairing code
                    </label>
                    <Input
                        id="pairing-code"
                        v-model="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="e.g. 48291376"
                        class="h-14 rounded-2xl bg-white/5 text-center text-2xl font-black tracking-[0.3em] text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-slate-400"
                        for="device-name"
                    >
                        Device name
                    </label>
                    <Input
                        id="device-name"
                        v-model="deviceName"
                        placeholder="Alex's iPhone"
                        class="h-12 rounded-2xl bg-white/5 text-white"
                    />
                </div>

                <p
                    v-if="error"
                    class="rounded-2xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-300"
                >
                    {{ error }}
                </p>

                <Button
                    type="submit"
                    class="h-14 w-full rounded-2xl bg-sky-500 text-base font-black text-slate-950 hover:bg-sky-400"
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? 'Pairing…' : 'Pair this phone' }}
                </Button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-500">
                Tip: add this page to your Home Screen for a full-screen app.
            </p>
        </div>
    </div>
</template>
