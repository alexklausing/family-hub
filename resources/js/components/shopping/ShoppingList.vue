<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import {
    ShoppingCart,
    Plus,
    RefreshCw,
    Eraser,
    PackageCheck,
} from 'lucide-vue-next'
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
            params: { sync: sync ? 1 : undefined },
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
            name: newItemName.value,
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
            purchased: !item.purchased,
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
    <div class="flex h-full flex-col gap-8 p-2">
        <!-- Add Item Bar (iOS Style) -->
        <div class="mx-auto flex w-full max-w-3xl items-center gap-4">
            <div class="group relative flex-1">
                <ShoppingCart
                    class="text-muted-foreground absolute top-1/2 left-4 h-6 w-6 -translate-y-1/2 opacity-40 transition-opacity group-focus-within:opacity-100"
                />
                <Input
                    v-model="newItemName"
                    placeholder="Add something to your list..."
                    class="focus:ring-primary/20 h-16 rounded-3xl border-white/20 bg-white/40 pl-12 text-2xl font-bold tracking-tight shadow-xl backdrop-blur-xl transition-all dark:border-white/10 dark:bg-white/5"
                    @keyup.enter="addItem"
                />
            </div>
            <Button
                class="shadow-primary/20 h-16 rounded-[2rem] px-10 text-xl font-black shadow-lg transition-all hover:scale-105 active:scale-95"
                @click="addItem"
            >
                <Plus class="mr-2 h-7 w-7" /> Add
            </Button>

            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-16 w-16 rounded-[2rem] border border-white/20 bg-white/40 shadow-xl backdrop-blur-xl dark:border-white/10 dark:bg-white/5"
                    @click="fetchItems(true)"
                    :disabled="isSyncing"
                >
                    <RefreshCw
                        :class="['h-7 w-7', isSyncing ? 'animate-spin' : '']"
                    />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="bg-destructive/10 dark:bg-destructive/20 border-destructive/20 text-destructive hover:bg-destructive h-16 w-16 rounded-[2rem] border shadow-xl transition-all hover:text-white"
                    @click="isClearDialogOpen = true"
                >
                    <Eraser class="h-7 w-7" />
                </Button>
            </div>
        </div>

        <div v-if="isLoading" class="flex flex-1 items-center justify-center">
            <RefreshCw class="text-primary h-16 w-16 animate-spin" />
        </div>

        <div
            v-else-if="Object.keys(categories).length === 0"
            class="flex flex-1 flex-col items-center justify-center opacity-30"
        >
            <ShoppingCart class="mb-6 h-40 w-40" />
            <p class="text-4xl font-black tracking-tighter uppercase italic">
                Shopping List Empty
            </p>
        </div>

        <div v-else class="custom-scrollbar flex-1 overflow-y-auto pr-6 pb-10">
            <div class="grid grid-cols-1 gap-10 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="(items, category) in categories"
                    :key="category"
                    class="space-y-6"
                >
                    <h3
                        class="text-primary flex items-center gap-4 pl-2 text-2xl font-black tracking-widest uppercase"
                    >
                        <div
                            class="bg-primary shadow-primary/20 h-8 w-3 rounded-full shadow-lg"
                        ></div>
                        {{ category || 'General' }}
                        <span
                            class="ml-auto text-sm font-bold tabular-nums opacity-40"
                            >{{ items.length }} Items</span
                        >
                    </h3>

                    <div class="space-y-3">
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="group flex items-center space-x-5 rounded-[2rem] border-none p-6 shadow-md"
                            :class="{
                                'scale-95 opacity-40 grayscale': item.purchased,
                            }"
                            @click="toggleItem(item)"
                        >
                            <Checkbox
                                :id="'item-' + item.id"
                                :checked="item.purchased"
                                @update:checked="toggleItem(item)"
                                class="h-8 w-8 rounded-xl border-2 transition-transform active:scale-75"
                                @click.stop
                            />
                            <div class="min-w-0 flex-1">
                                <Label
                                    :for="'item-' + item.id"
                                    class="block cursor-pointer truncate text-2xl font-black transition-all select-none"
                                    :class="{
                                        'line-through opacity-50':
                                            item.purchased,
                                    }"
                                >
                                    {{ item.name }}
                                </Label>
                                <p
                                    v-if="item.quantity"
                                    class="text-muted-foreground mt-1 text-sm font-bold tracking-widest uppercase"
                                >
                                    {{ item.quantity }}
                                </p>
                            </div>
                            <PackageCheck
                                v-if="item.purchased"
                                class="text-primary h-6 w-6 opacity-50"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog (iOS Style) -->
        <Dialog v-model:open="isClearDialogOpen">
            <DialogContent
                class="animate-in fade-in zoom-in-95 rounded-[3rem] border-none bg-white/95 p-10 shadow-2xl backdrop-blur-3xl duration-300 sm:max-w-[500px] dark:bg-black/95"
            >
                <DialogHeader class="text-center">
                    <div
                        class="bg-destructive/10 text-destructive mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full"
                    >
                        <Eraser class="h-10 w-10" />
                    </div>
                    <DialogTitle class="text-4xl font-black tracking-tight"
                        >Clear Entire List?</DialogTitle
                    >
                    <DialogDescription
                        class="mt-2 text-xl font-bold opacity-60"
                    >
                        Every single item will be permanently removed from
                        Paprika.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-8 flex flex-col gap-4 sm:flex-col">
                    <Button
                        class="bg-destructive hover:bg-destructive/90 shadow-destructive/20 h-18 w-full rounded-[2rem] py-6 text-2xl font-black shadow-xl transition-all active:scale-95"
                        @click="clearList"
                        >Yes, Wipe it Clean</Button
                    >
                    <Button
                        variant="ghost"
                        class="h-18 w-full rounded-[2rem] py-6 text-xl font-bold opacity-60 transition-all hover:opacity-100"
                        @click="isClearDialogOpen = false"
                        >Cancel</Button
                    >
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
