<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const countdowns = ref([])
const isDialogOpen = ref(false)
const dialogMode = ref('add')
const form = ref({ id: null, title: '', target_date: '', icon: '' })

const fetchCountdowns = async () => {
    try {
        const res = await axios.get('/api/countdowns')
        countdowns.value = res.data
    } catch (e) {
        console.error('Failed to fetch countdowns', e)
    }
}

onMounted(() => {
    fetchCountdowns()
})

const getDaysRemaining = (targetDateStr) => {
    const today = new Date()
    today.setHours(0,0,0,0)
    
    let targetStr = targetDateStr
    if (targetStr && targetStr.includes('T')) {
        targetStr = targetStr.split('T')[0]
    }
    
    if (!targetStr) return 0
    
    const parts = targetStr.split('-')
    const target = new Date(parts[0], parts[1] - 1, parts[2])
    target.setHours(0,0,0,0)
    
    const diffTime = target - today
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    
    return diffDays
}

const formatRemainingText = (days) => {
    if (days === 0) return 'Today'
    if (days === 1) return 'Tomorrow'
    if (days < 0) return `${Math.abs(days)} days ago`
    return `In ${days} days`
}

const openAdd = () => {
    dialogMode.value = 'add'
    form.value = { id: null, title: '', target_date: '', icon: '🌟' }
    isDialogOpen.value = true
}

const openEdit = (c) => {
    dialogMode.value = 'edit'
    form.value = { ...c }
    if (form.value.target_date && form.value.target_date.includes('T')) {
        form.value.target_date = form.value.target_date.split('T')[0]
    }
    isDialogOpen.value = true
}

const save = async () => {
    try {
        if (dialogMode.value === 'add') {
            await axios.post('/api/countdowns', form.value)
        } else {
            await axios.put(`/api/countdowns/${form.value.id}`, form.value)
        }
        isDialogOpen.value = false
        fetchCountdowns()
    } catch (e) {
        console.error('Failed to save countdown', e)
    }
}

const deleteCountdown = async (id) => {
    if (confirm('Delete this countdown?')) {
        try {
            await axios.delete(`/api/countdowns/${id}`)
            fetchCountdowns()
        } catch (e) {
            console.error('Failed to delete countdown', e)
        }
    }
}
</script>

<template>
    <div class="mt-8 flex flex-col gap-3">
        <div class="flex items-center justify-between px-2">
            <h3 class="text-sm font-black tracking-widest uppercase text-black/40 dark:text-white/40">Countdowns</h3>
            <Button variant="ghost" size="icon" class="h-6 w-6 rounded-full opacity-50 hover:bg-black/5 dark:hover:bg-white/10 hover:opacity-100" @click="openAdd">
                <Plus class="h-4 w-4" />
            </Button>
        </div>

        <div class="flex flex-col gap-2 px-2 pb-4">
            <div 
                v-for="c in countdowns" 
                :key="c.id"
                class="group relative flex items-center gap-4 rounded-[1.25rem] border border-slate-200 bg-white/50 dark:border-white/5 dark:bg-white/5 p-3 transition-all hover:bg-slate-50 dark:hover:bg-white/10 shadow-sm dark:shadow-none"
            >
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-white/10 text-2xl shadow-inner">
                    {{ c.icon }}
                </div>
                
                <div class="flex min-w-0 flex-1 flex-col justify-center gap-0.5">
                    <span class="truncate text-base font-bold leading-tight tracking-tight text-slate-900 dark:text-white/90">{{ c.title }}</span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-primary">
                        {{ formatRemainingText(getDaysRemaining(c.target_date)) }}
                    </span>
                </div>
                
                <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1 opacity-0 backdrop-blur-md transition-opacity group-hover:opacity-100 bg-white/80 dark:bg-transparent rounded-full px-1 py-1">
                    <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full bg-slate-200/50 hover:bg-slate-300 dark:bg-black/20 dark:hover:bg-black/40" @click="openEdit(c)">
                        <Pencil class="h-4 w-4 text-slate-700 dark:text-white/80" />
                    </Button>
                    <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full bg-slate-200/50 text-red-500 hover:bg-slate-300 hover:text-red-600 dark:bg-black/20 dark:text-red-400 dark:hover:bg-black/40 dark:hover:text-red-300" @click="deleteCountdown(c.id)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
            <DialogContent class="sm:max-w-[425px] rounded-3xl border-none bg-white/95 p-8 shadow-2xl backdrop-blur-3xl dark:bg-black/95 text-slate-900 dark:text-white">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">{{ dialogMode === 'add' ? 'Add' : 'Edit' }} Countdown</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="title" class="font-bold">Title</Label>
                        <Input id="title" v-model="form.title" class="bg-white dark:bg-white/5 border-slate-200 dark:border-white/10 rounded-xl" placeholder="e.g. Disney Trip" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="date" class="font-bold">Target Date</Label>
                        <Input id="date" type="date" v-model="form.target_date" class="block w-full bg-white dark:bg-white/5 border-slate-200 dark:border-white/10 rounded-xl" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="icon" class="font-bold">Icon (Emoji)</Label>
                        <Input id="icon" v-model="form.icon" class="bg-white dark:bg-white/5 border-slate-200 dark:border-white/10 rounded-xl" placeholder="🌟" />
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
