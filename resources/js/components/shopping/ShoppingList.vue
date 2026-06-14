<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { ShoppingCart, Plus, RefreshCw, Eraser, PackageCheck } from 'lucide-vue-next'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'

const categories = ref({})
const newItemName = ref('')
const isLoading = ref(false)
const isSyncing = ref(false)
const isClearDialogOpen = ref(false)

const fetchItems = async (sync = false) => {
    isLoading.value = sync ? false : true
    if (sync) isSyncing.value = true
    
    try {
        const response = await axios.get('/api/shopping-list', {
            params: { sync: sync ? 1 : undefined }
        })
        categories.value = response.data
    } catch (error) {
        console.error('Failed to fetch shopping list:', error)
    } finally {
        isLoading.value = false
        isSyncing.value = false
    }
}

const addItem = async () => {
    if (!newItemName.value) return
    
    try {
        await axios.post('/api/shopping-list', {
            name: newItemName.value
        })
        newItemName.value = ''
        fetchItems()
    } catch (error) {
        console.error('Failed to add item:', error)
    }
}

const toggleItem = async (item) => {
    try {
        await axios.post(`/api/shopping-list/${item.id}/toggle`, {
            purchased: !item.purchased
        })
        item.purchased = !item.purchased
    } catch (error) {
        console.error('Failed to toggle item:', error)
        item.purchased = !item.purchased
    }
}

const clearList = async () => {
    isClearDialogOpen.value = false
    isLoading.value = true
    try {
        await axios.delete('/api/shopping-list')
        fetchItems()
    } catch (error) {
        console.error('Failed to clear list:', error)
        isLoading.value = false
    }
}

onMounted(() => {
    fetchItems()
})
</script>

<template>
    <div class="flex flex-col gap-8 h-full p-2">
        <!-- Add Item Bar (iOS Style) -->
        <div class="flex gap-4 items-center max-w-3xl mx-auto w-full">
            <div class="relative flex-1 group">
                <ShoppingCart class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-muted-foreground opacity-40 group-focus-within:opacity-100 transition-opacity" />
                <Input 
                    v-model="newItemName" 
                    placeholder="Add something to your list..." 
                    class="pl-12 h-16 text-2xl rounded-3xl bg-white/40 dark:bg-white/5 backdrop-blur-xl border-white/20 dark:border-white/10 shadow-xl focus:ring-primary/20 transition-all font-bold tracking-tight"
                    @keyup.enter="addItem"
                />
            </div>
            <Button class="h-16 px-10 rounded-[2rem] font-black text-xl shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all" @click="addItem">
                <Plus class="mr-2 h-7 w-7" /> Add
            </Button>
            
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" class="h-16 w-16 rounded-[2rem] bg-white/40 dark:bg-white/5 backdrop-blur-xl border border-white/20 dark:border-white/10 shadow-xl" @click="fetchItems(true)" :disabled="isSyncing">
                    <RefreshCw :class="['h-7 w-7', isSyncing ? 'animate-spin' : '']" />
                </Button>
                <Button variant="ghost" size="icon" class="h-16 w-16 rounded-[2rem] bg-destructive/10 dark:bg-destructive/20 border border-destructive/20 text-destructive shadow-xl hover:bg-destructive hover:text-white transition-all" @click="isClearDialogOpen = true">
                    <Eraser class="h-7 w-7" />
                </Button>
            </div>
        </div>

        <div v-if="isLoading" class="flex-1 flex items-center justify-center">
            <RefreshCw class="h-16 w-16 animate-spin text-primary" />
        </div>

        <div v-else-if="Object.keys(categories).length === 0" class="flex-1 flex flex-col items-center justify-center opacity-30">
            <ShoppingCart class="h-40 w-40 mb-6" />
            <p class="text-4xl font-black uppercase tracking-tighter italic">Shopping List Empty</p>
        </div>

        <div v-else class="flex-1 overflow-y-auto pr-6 custom-scrollbar pb-10">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-10">
                <div v-for="(items, category) in categories" :key="category" class="space-y-6">
                    <h3 class="text-2xl font-black uppercase tracking-widest text-primary flex items-center gap-4 pl-2">
                        <div class="w-3 h-8 bg-primary rounded-full shadow-lg shadow-primary/20"></div>
                        {{ category || 'General' }}
                        <span class="ml-auto text-sm opacity-40 font-bold tabular-nums">{{ items.length }} Items</span>
                    </h3>
                    
                    <div class="space-y-3">
                        <div v-for="item in items" :key="item.id" 
                             class="flex items-center space-x-5 p-6 rounded-[2rem] border-none shadow-md group"
                             :class="{ 'opacity-40 grayscale scale-95': item.purchased }"
                             @click="toggleItem(item)"
                        >
                            <Checkbox 
                                :id="'item-' + item.id" 
                                :checked="item.purchased"
                                @update:checked="toggleItem(item)"
                                class="h-8 w-8 rounded-xl border-2 transition-transform active:scale-75"
                                @click.stop
                            />
                            <div class="flex-1 min-w-0">
                                <Label 
                                    :for="'item-' + item.id" 
                                    class="text-2xl font-black cursor-pointer select-none transition-all block truncate"
                                    :class="{ 'line-through opacity-50': item.purchased }"
                                >
                                    {{ item.name }}
                                </Label>
                                <p v-if="item.quantity" class="text-sm font-bold uppercase tracking-widest text-muted-foreground mt-1">{{ item.quantity }}</p>
                            </div>
                            <PackageCheck v-if="item.purchased" class="w-6 h-6 text-primary opacity-50" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog (iOS Style) -->
        <Dialog v-model:open="isClearDialogOpen">
            <DialogContent class="sm:max-w-[500px] rounded-[3rem] p-10 border-none bg-white/95 dark:bg-black/95 backdrop-blur-3xl shadow-2xl animate-in fade-in zoom-in-95 duration-300">
                <DialogHeader class="text-center">
                    <div class="w-20 h-20 bg-destructive/10 text-destructive rounded-full flex items-center justify-center mx-auto mb-6">
                        <Eraser class="w-10 h-10" />
                    </div>
                    <DialogTitle class="text-4xl font-black tracking-tight">Clear Entire List?</DialogTitle>
                    <DialogDescription class="text-xl font-bold opacity-60 mt-2">
                        Every single item will be permanently removed from Paprika.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex flex-col sm:flex-col gap-4 mt-8">
                    <Button class="w-full h-18 py-6 rounded-[2rem] text-2xl font-black bg-destructive hover:bg-destructive/90 shadow-xl shadow-destructive/20 transition-all active:scale-95" @click="clearList">Yes, Wipe it Clean</Button>
                    <Button variant="ghost" class="w-full h-18 py-6 rounded-[2rem] text-xl font-bold opacity-60 hover:opacity-100 transition-all" @click="isClearDialogOpen = false">Cancel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 12px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground) / 0.1);
    border-radius: 20px;
    border: 4px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.3);
    background-clip: content-box;
}
</style>
