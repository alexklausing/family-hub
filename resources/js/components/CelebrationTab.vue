<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Settings, Plus, Pencil, Trash2, X, Star, StarOff, PartyPopper } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const celebrations = ref([])
const isLoading = ref(true)
const loadError = ref(false)
const isManageOpen = ref(false)
const isDialogOpen = ref(false)
const dialogMode = ref('add')
const form = ref({ id: null, message: '', background: 'sunset', font: 'display', font_color: '#ffffff' })

const backgrounds = {
    sunset: 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fdfcfb 100%)',
    ocean: 'linear-gradient(135deg, #2b5876 0%, #4e4376 100%)',
    forest: 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)',
    confetti: 'linear-gradient(135deg, #fc5c7d 0%, #6a82fb 100%)',
    royal: 'linear-gradient(135deg, #141e30 0%, #243b55 100%)',
    candy: 'linear-gradient(135deg, #ff6fd8 0%, #3813c2 100%)',
    night: 'linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)',
    gold: 'linear-gradient(135deg, #f7971e 0%, #ffd200 100%)',
}

const fonts = {
    display: { label: 'Display', family: 'Impact, "Arial Black", sans-serif' },
    serif: { label: 'Serif', family: 'Georgia, "Times New Roman", serif' },
    cursive: { label: 'Cursive', family: '"Comic Sans MS", "Segoe Script", cursive' },
    mono: { label: 'Mono', family: '"Courier New", monospace' },
    sans: { label: 'Sans', family: 'system-ui, sans-serif' },
}

const fontColors = ['#ffffff', '#000000', '#ffd700', '#ff6b6b', '#4ade80', '#60a5fa', '#f472b6', '#fb923c']

const activeCelebration = computed(() => celebrations.value.find((c) => c.is_active) || null)

const fetchCelebrations = async () => {
    isLoading.value = true
    loadError.value = false
    try {
        const res = await axios.get('/api/celebrations')
        celebrations.value = res.data
    } catch (e) {
        console.error('Failed to fetch celebrations', e)
        loadError.value = true
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchCelebrations()
})

const openAdd = () => {
    dialogMode.value = 'add'
    form.value = { id: null, message: '', background: 'sunset', font: 'display', font_color: '#ffffff' }
    isDialogOpen.value = true
}

const openEdit = (c) => {
    dialogMode.value = 'edit'
    form.value = { ...c }
    isDialogOpen.value = true
}

const setActive = async (c) => {
    try {
        await axios.put(`/api/celebrations/${c.id}`, { is_active: !c.is_active })
        fetchCelebrations()
    } catch (e) {
        console.error('Failed to set active celebration', e)
    }
}

const save = async () => {
    try {
        if (dialogMode.value === 'add') {
            await axios.post('/api/celebrations', form.value)
        } else {
            await axios.put(`/api/celebrations/${form.value.id}`, form.value)
        }
        isDialogOpen.value = false
        fetchCelebrations()
    } catch (e) {
        console.error('Failed to save celebration', e)
    }
}

const deleteCelebration = async (id) => {
    if (confirm('Delete this celebration?')) {
        try {
            await axios.delete(`/api/celebrations/${id}`)
            fetchCelebrations()
        } catch (e) {
            console.error('Failed to delete celebration', e)
        }
    }
}
</script>

<template>
    <div class="w-full h-full relative overflow-hidden">
        <!-- Fullscreen Celebration Display -->
        <div
            class="w-full h-full flex items-center justify-center p-8"
            :style="{ background: activeCelebration ? backgrounds[activeCelebration.background] || backgrounds.sunset : 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)' }"
        >
            <div
                v-if="activeCelebration"
                class="max-w-5xl text-center leading-tight break-words"
                :style="{
                    fontFamily: fonts[activeCelebration.font]?.family || fonts.display.family,
                    color: activeCelebration.font_color,
                    textShadow: '0 4px 30px rgba(0,0,0,0.35)',
                }"
            >
                <div class="text-5xl sm:text-7xl md:text-8xl font-black italic tracking-tight">
                    {{ activeCelebration.message }}
                </div>
            </div>
            <div v-else class="flex flex-col items-center gap-6 text-center">
                <PartyPopper class="h-16 w-16 text-white/60" />
                <p class="text-white/70 text-xl font-bold">No celebration set yet.</p>
                <Button variant="outline" class="rounded-2xl font-bold bg-white/10 border-white/20 text-white hover:bg-white/20" @click="openAdd">
                    <Plus class="h-4 w-4 mr-2" /> Create a Celebration
                </Button>
            </div>
        </div>

        <!-- Manage Toggle -->
        <Button
            variant="ghost"
            size="icon"
            class="absolute top-3 right-3 h-11 w-11 rounded-2xl bg-black/20 text-white/80 backdrop-blur-md hover:bg-black/30 hover:text-white shadow-lg"
            @click="isManageOpen = !isManageOpen"
        >
            <Settings class="h-5 w-5" />
        </Button>

        <!-- Manage Panel -->
        <div
            v-if="isManageOpen"
            class="absolute inset-0 z-10 bg-white/95 dark:bg-black/95 backdrop-blur-2xl overflow-y-auto custom-scrollbar"
        >
            <div class="flex flex-col gap-3 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black tracking-widest uppercase text-black/40 dark:text-white/40">Celebrations</h3>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full opacity-60 hover:bg-black/5 dark:hover:bg-white/10" @click="openAdd">
                            <Plus class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full opacity-60 hover:bg-black/5 dark:hover:bg-white/10" @click="isManageOpen = false">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div
                        v-for="c in celebrations"
                        :key="c.id"
                        class="group relative flex items-center gap-4 rounded-[1.25rem] border border-slate-200 bg-white/50 dark:border-white/5 dark:bg-white/5 p-3 transition-all shadow-sm dark:shadow-none"
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-[10px] font-black text-center px-1 overflow-hidden"
                            :style="{ background: backgrounds[c.background] || backgrounds.sunset, color: c.font_color }"
                        >
                            {{ c.message.slice(0, 14) }}{{ c.message.length > 14 ? '…' : '' }}
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col justify-center gap-0.5">
                            <span class="truncate text-base font-bold leading-tight tracking-tight text-slate-900 dark:text-white/90">{{ c.message }}</span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary">
                                {{ fonts[c.font]?.label || c.font }} · {{ c.background }}
                            </span>
                        </div>

                        <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1 opacity-0 backdrop-blur-md transition-opacity group-hover:opacity-100 bg-white/80 dark:bg-transparent rounded-full px-1 py-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full bg-slate-200/50 hover:bg-slate-300 dark:bg-black/20 dark:hover:bg-black/40" :title="c.is_active ? 'Hide from hub' : 'Show on hub'" @click="setActive(c)">
                                <StarOff v-if="c.is_active" class="h-4 w-4 text-amber-500" />
                                <Star v-else class="h-4 w-4 text-slate-600 dark:text-white/80" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full bg-slate-200/50 hover:bg-slate-300 dark:bg-black/20 dark:hover:bg-black/40" @click="openEdit(c)">
                                <Pencil class="h-4 w-4 text-slate-700 dark:text-white/80" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full bg-slate-200/50 text-red-500 hover:bg-slate-300 hover:text-red-600 dark:bg-black/20 dark:text-red-400 dark:hover:bg-black/40 dark:hover:text-red-300" @click="deleteCelebration(c.id)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <p v-if="celebrations.length === 0" class="text-center text-sm font-semibold text-black/40 dark:text-white/40 py-8">
                        No celebrations yet. Tap + to add one.
                    </p>
                </div>
            </div>
        </div>

        <!-- Add/Edit Dialog -->
        <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
            <DialogContent class="sm:max-w-[425px] rounded-3xl border-none bg-white/95 p-8 shadow-2xl backdrop-blur-3xl dark:bg-black/95 text-slate-900 dark:text-white">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">{{ dialogMode === 'add' ? 'New' : 'Edit' }} Celebration</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="message" class="font-bold">Message</Label>
                        <Input id="message" v-model="form.message" class="bg-white dark:bg-white/5 border-slate-200 dark:border-white/10 rounded-xl" placeholder="e.g. Happy Birthday!" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="background" class="font-bold">Background</Label>
                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="(bg, key) in backgrounds"
                                :key="key"
                                type="button"
                                class="h-12 rounded-xl border-2 transition-all"
                                :class="form.background === key ? 'border-slate-900 dark:border-white scale-105' : 'border-transparent'"
                                :style="{ background: bg }"
                                @click="form.background = key"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="font" class="font-bold">Font</Label>
                        <div class="grid grid-cols-5 gap-2">
                            <button
                                v-for="(font, key) in fonts"
                                :key="key"
                                type="button"
                                class="h-10 rounded-xl border-2 text-sm font-bold transition-all"
                                :class="form.font === key ? 'border-slate-900 dark:border-white bg-slate-100 dark:bg-white/10' : 'border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5'"
                                :style="{ fontFamily: font.family }"
                                @click="form.font = key"
                            >
                                Aa
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="font_color" class="font-bold">Font Color</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="color in fontColors"
                                :key="color"
                                type="button"
                                class="h-10 w-10 rounded-full border-2 transition-all"
                                :class="form.font_color === color ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent'"
                                :style="{ background: color }"
                                @click="form.font_color = color"
                            />
                        </div>
                    </div>
                    <div class="mt-2 rounded-2xl p-4 flex items-center justify-center"
                        :style="{ background: backgrounds[form.background] || backgrounds.sunset }"
                    >
                        <span
                            class="text-xl font-black italic tracking-tight text-center"
                            :style="{ fontFamily: fonts[form.font]?.family || fonts.display.family, color: form.font_color, textShadow: '0 2px 12px rgba(0,0,0,0.3)' }"
                        >
                            {{ form.message || 'Preview' }}
                        </span>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" class="rounded-xl font-bold" @click="isDialogOpen = false">Cancel</Button>
                    <Button class="rounded-xl font-bold" @click="save">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
