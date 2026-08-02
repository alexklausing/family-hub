<script setup>
import { computed, ref } from 'vue'
import { useStorage } from '@vueuse/core'
import draggable from 'vuedraggable'
import { Globe, Settings, Check, Volume2, GripVertical } from 'lucide-vue-next'
import { useLongPress } from '@/composables/useLongPress'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'

// Enabled languages state
const enabledLanguages = useStorage('word-of-day-languages', ['fr', 'de', 'no', 'lb'])

// Display order of languages (persisted, reorderable via long-press + drag)
const languageOrder = useStorage('word-of-day-language-order', ['fr', 'de', 'no', 'es', 'lb'])

const availableLanguages = [
    { id: 'fr', name: 'French', flag: '🇫🇷' },
    { id: 'de', name: 'German', flag: '🇩🇪' },
    { id: 'no', name: 'Norwegian', flag: '🇳🇴' },
    { id: 'es', name: 'Spanish', flag: '🇪🇸' },
    { id: 'lb', name: 'Luxembourgish', flag: '🇱🇺' },
]

// Languages sorted by the persisted order, with any newly added languages appended
const orderedLanguages = computed(() => {
    const ordered = languageOrder.value
        .map((id) => availableLanguages.find((l) => l.id === id))
        .filter(Boolean)
    const rest = availableLanguages.filter(
        (l) => !ordered.some((o) => o.id === l.id),
    )
    return [...ordered, ...rest]
})

const isSettingsOpen = ref(false)
const isReorderMode = ref(false)

const toggleLanguage = (langId) => {
    if (enabledLanguages.value.includes(langId)) {
        enabledLanguages.value = enabledLanguages.value.filter(id => id !== langId)
    } else {
        enabledLanguages.value.push(langId)
    }
}

// Long-press a language to enter reorder mode; short tap toggles it
const langHandlers = {}
for (const lang of availableLanguages) {
    langHandlers[lang.id] = useLongPress(
        () => {
            isReorderMode.value = true
        },
        () => {
            toggleLanguage(lang.id)
        },
    )
}

// 31 days of phrases for a rotating schedule
const phrases = [
    { en: "Hello", fr: "Bonjour", de: "Hallo", no: "Hallo", es: "Hola", lb: "Moien" },
    { en: "Thank you", fr: "Merci", de: "Danke", no: "Takk", es: "Gracias", lb: "Merci" },
    { en: "Please", fr: "S'il vous plaît", de: "Bitte", no: "Vær så snill", es: "Por favor", lb: "Wann ech gelift" },
    { en: "Good morning", fr: "Bonjour", de: "Guten Morgen", no: "God morgen", es: "Buenos días", lb: "Gudde Moien" },
    { en: "Good night", fr: "Bonne nuit", de: "Gute Nacht", no: "God natt", es: "Buenas noches", lb: "Gutt Nuecht" },
    { en: "Yes", fr: "Oui", de: "Ja", no: "Ja", es: "Sí", lb: "Jo" },
    { en: "No", fr: "Non", de: "Nein", no: "Nei", es: "No", lb: "Nee" },
    { en: "Goodbye", fr: "Au revoir", de: "Auf Wiedersehen", no: "Ha det bra", es: "Adiós", lb: "Äddi" },
    { en: "How are you?", fr: "Comment allez-vous ?", de: "Wie geht es dir?", no: "Hvordan har du det?", es: "¿Cómo estás?", lb: "Wéi geet et dir?" },
    { en: "I am fine", fr: "Je vais bien", de: "Mir geht es gut", no: "Jeg har det bra", es: "Estoy bien", lb: "Mir geet et gutt" },
    { en: "Excuse me", fr: "Excusez-moi", de: "Entschuldigung", no: "Unnskyld meg", es: "Disculpe", lb: "Pardon" },
    { en: "I'm sorry", fr: "Je suis désolé", de: "Es tut mir leid", no: "Beklager", es: "Lo siento", lb: "Et deet mir leed" },
    { en: "What is your name?", fr: "Comment vous appelez-vous ?", de: "Wie heißt du?", no: "Hva heter du?", es: "¿Cómo te llamas?", lb: "Wéi heeschs du?" },
    { en: "My name is...", fr: "Je m'appelle...", de: "Ich heiße...", no: "Jeg heter...", es: "Me llamo...", lb: "Ech heeschen..." },
    { en: "Where is the bathroom?", fr: "Où sont les toilettes ?", de: "Wo ist die Toilette?", no: "Hvor er toalettet?", es: "¿Dónde está el baño?", lb: "Wou ass d'Toilette?" },
    { en: "How much is this?", fr: "Combien ça coûte ?", de: "Wie viel kostet das?", no: "Hvor mye koster dette?", es: "¿Cuánto cuesta esto?", lb: "Wéi vill kascht dat?" },
    { en: "I don't understand", fr: "Je ne comprends pas", de: "Ich verstehe nicht", no: "Jeg forstår ikke", es: "No entiendo", lb: "Ech verstinn net" },
    { en: "Do you speak English?", fr: "Parlez-vous anglais ?", de: "Sprechen Sie Englisch?", no: "Snakker du engelsk?", es: "¿Hablas inglés?", lb: "Schwätz dir Englesch?" },
    { en: "I love you", fr: "Je t'aime", de: "Ich liebe dich", no: "Jeg elsker deg", es: "Te amo", lb: "Ech hunn dech gär" },
    { en: "Water", fr: "Eau", de: "Wasser", no: "Vann", es: "Agua", lb: "Waasser" },
    { en: "Food", fr: "Nourriture", de: "Essen", no: "Mat", es: "Comida", lb: "Iessen" },
    { en: "Delicious", fr: "Délicieux", de: "Lecker", no: "Deilig", es: "Delicioso", lb: "Lecker" },
    { en: "Beautiful", fr: "Beau / Belle", de: "Schön", no: "Vakker", es: "Hermoso", lb: "Schéin" },
    { en: "Friend", fr: "Ami", de: "Freund", no: "Venn", es: "Amigo", lb: "Frënd" },
    { en: "Family", fr: "Famille", de: "Familie", no: "Familie", es: "Familia", lb: "Famill" },
    { en: "Today", fr: "Aujourd'hui", de: "Heute", no: "I dag", es: "Hoy", lb: "Haut" },
    { en: "Tomorrow", fr: "Demain", de: "Morgen", no: "I morgen", es: "Mañana", lb: "Muer" },
    { en: "Always", fr: "Toujours", de: "Immer", no: "Alltid", es: "Siempre", lb: "Ëmmer" },
    { en: "Happy", fr: "Heureux", de: "Glücklich", no: "Lykkelig", es: "Feliz", lb: "Glécklech" },
    { en: "Tired", fr: "Fatigué", de: "Müde", no: "Trøtt", es: "Cansado", lb: "Midd" },
    { en: "Let's go!", fr: "Allons-y !", de: "Lass uns gehen!", no: "La oss gå!", es: "¡Vamos!", lb: "Mir ginn!" }
]

const getWordOfTheDay = () => {
    // Determine the day of the year to cycle through the words predictably
    const now = new Date()
    const start = new Date(now.getFullYear(), 0, 0)
    const diff = (now - start) + ((start.getTimezoneOffset() - now.getTimezoneOffset()) * 60 * 1000)
    const oneDay = 1000 * 60 * 60 * 24
    const dayOfYear = Math.floor(diff / oneDay)
    return phrases[dayOfYear % phrases.length]
}

const currentWord = computed(() => getWordOfTheDay())

const getVoiceLang = (langId) => {
    const map = {
        en: 'en-US',
        fr: 'fr-FR',
        de: 'de-DE',
        no: 'nb-NO',
        es: 'es-ES',
        lb: 'lb-LU'
    }
    return map[langId] || 'en-US'
}

const speak = (text, langId) => {
    // Chromium Snap on Ubuntu blocks speech-dispatcher. If no voices are found, fallback to cloud TTS.
    const voices = window.speechSynthesis ? window.speechSynthesis.getVoices() : []
    
    if (!window.speechSynthesis || voices.length === 0) {
        const audioUrl = `https://translate.google.com/translate_tts?ie=UTF-8&q=${encodeURIComponent(text)}&tl=${langId}&client=tw-ob`
        const audio = new Audio(audioUrl)
        // Google's translate_tts endpoint returns 404 when a Referer header is present.
        // Chromium does not honor element-level referrerPolicy for media loads, so the
        // document-level <meta name="referrer" content="no-referrer"> in the page head
        // is what actually strips the Referer. This property is kept as defense-in-depth
        // for browsers that do honor it (Firefox/Safari).
        audio.referrerPolicy = 'no-referrer'
        audio.play().catch(e => console.error("Audio fallback failed:", e))
        return
    }

    window.speechSynthesis.cancel()

    const utterance = new SpeechSynthesisUtterance(text)
    utterance.lang = getVoiceLang(langId)
    
    const targetLang = getVoiceLang(langId)
    const voice = voices.find(v => v.lang === targetLang || v.lang.startsWith(targetLang.split('-')[0]))
    if (voice) {
        utterance.voice = voice
    }

    window.speechSynthesis.speak(utterance)
}
</script>

<template>
    <div class="h-full w-full flex flex-col gap-3 pt-8">
        <div class="flex items-center justify-between px-2">
            <h3 class="text-sm font-black tracking-widest uppercase text-black/40 dark:text-white/40 flex items-center gap-2">
                <Globe class="h-4 w-4" />
                Word of the Day
            </h3>
            <Button variant="ghost" size="icon" class="h-6 w-6 rounded-full opacity-50 hover:bg-black/5 dark:hover:bg-white/10 hover:opacity-100" @click="isSettingsOpen = true">
                <Settings class="h-4 w-4" />
            </Button>
        </div>

        <div class="flex-1 min-h-0 flex flex-col overflow-y-auto p-4">
            <div class="w-full max-w-sm rounded-[2rem] border border-slate-200 bg-white/50 dark:border-white/5 dark:bg-white/5 p-8 shadow-sm flex flex-col items-center gap-8 m-auto">
                <!-- English Word -->
                <div class="text-center cursor-pointer group hover:opacity-70 transition-all active:scale-95 flex flex-col items-center" @click="speak(currentWord.en, 'en')">
                    <span class="text-sm font-bold uppercase tracking-widest text-primary mb-2 flex items-center gap-2">
                        English
                        <Volume2 class="h-3 w-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </span>
                    <h2 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ currentWord.en }}</h2>
                </div>

                <div class="h-px w-full bg-slate-200 dark:bg-white/10"></div>

                <!-- Translations -->
                <div class="w-full flex flex-col gap-4">
                    <template v-for="lang in orderedLanguages" :key="lang.id">
                        <div 
                            v-if="enabledLanguages.includes(lang.id)" 
                            class="flex flex-col items-center text-center cursor-pointer group hover:opacity-70 transition-all active:scale-95"
                            @click="speak(currentWord[lang.id], lang.id)"
                        >
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1">
                                <span>{{ lang.flag }}</span>
                                {{ lang.name }}
                                <Volume2 class="h-3 w-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity" />
                            </span>
                            <span class="text-2xl font-bold tracking-tight text-slate-700 dark:text-slate-200">{{ currentWord[lang.id] }}</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Settings Dialog -->
        <Dialog v-model:open="isSettingsOpen">
            <DialogContent class="rounded-3xl border-none bg-white/95 p-8 shadow-none backdrop-blur-3xl sm:max-w-[425px] dark:bg-black/95">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black uppercase italic">Language Settings</DialogTitle>
                    <DialogDescription class="text-[10px] font-bold tracking-widest uppercase italic opacity-60">
                        Choose which translations to display for the Word of the Day.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="isReorderMode"
                    class="flex items-center justify-between rounded-xl bg-primary/10 px-3 py-2 text-xs font-bold tracking-widest uppercase"
                >
                    <span>Drag to reorder</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-6 rounded-lg px-2 text-xs font-black uppercase"
                        @click="isReorderMode = false"
                    >
                        Done
                    </Button>
                </div>
                <p
                    v-else
                    class="text-[10px] font-bold tracking-widest uppercase italic opacity-40"
                >
                    Press &amp; hold a language to reorder
                </p>

                <draggable
                    v-model="languageOrder"
                    :item-key="(id) => id"
                    :disabled="!isReorderMode"
                    handle=".language-row"
                    :animation="200"
                    class="grid gap-4"
                    @end="isReorderMode = false"
                >
                    <template #item="{ element: langId }">
                        <div
                            class="language-row flex items-center justify-between gap-3 rounded-[1.5rem] border border-white/20 bg-white/40 p-4 transition-colors dark:bg-white/5"
                            :class="{
                                'ring-2 ring-primary bg-white/60 dark:bg-white/10': enabledLanguages.includes(langId),
                                'cursor-grab ring-1 ring-dashed ring-primary/40': isReorderMode,
                                'cursor-pointer': !isReorderMode,
                            }"
                            v-bind="isReorderMode ? {} : langHandlers[langId]"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <GripVertical
                                    v-if="isReorderMode"
                                    class="text-muted-foreground h-5 w-5 shrink-0 opacity-60"
                                />
                                <span class="text-2xl">{{ availableLanguages.find((l) => l.id === langId)?.flag }}</span>
                                <Label class="cursor-pointer truncate text-base font-bold">
                                    {{ availableLanguages.find((l) => l.id === langId)?.name }}
                                </Label>
                            </div>
                            <Switch
                                v-if="!isReorderMode"
                                :checked="enabledLanguages.includes(langId)"
                            />
                        </div>
                    </template>
                </draggable>

                <DialogFooter>
                    <Button class="rounded-xl font-bold w-full" @click="isSettingsOpen = false">Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
