<script setup>
import { computed, ref } from 'vue'
import { useStorage } from '@vueuse/core'
import { Globe, Settings, Check, Volume2 } from 'lucide-vue-next'
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
const enabledLanguages = useStorage('word-of-day-languages', ['fr', 'de', 'no'])

const availableLanguages = [
    { id: 'fr', name: 'French', flag: '🇫🇷' },
    { id: 'de', name: 'German', flag: '🇩🇪' },
    { id: 'no', name: 'Norwegian', flag: '🇳🇴' },
    { id: 'es', name: 'Spanish', flag: '🇪🇸' },
]

const isSettingsOpen = ref(false)

const toggleLanguage = (langId) => {
    if (enabledLanguages.value.includes(langId)) {
        enabledLanguages.value = enabledLanguages.value.filter(id => id !== langId)
    } else {
        enabledLanguages.value.push(langId)
    }
}

// 31 days of phrases for a rotating schedule
const phrases = [
    { en: "Hello", fr: "Bonjour", de: "Hallo", no: "Hallo", es: "Hola" },
    { en: "Thank you", fr: "Merci", de: "Danke", no: "Takk", es: "Gracias" },
    { en: "Please", fr: "S'il vous plaît", de: "Bitte", no: "Vær så snill", es: "Por favor" },
    { en: "Good morning", fr: "Bonjour", de: "Guten Morgen", no: "God morgen", es: "Buenos días" },
    { en: "Good night", fr: "Bonne nuit", de: "Gute Nacht", no: "God natt", es: "Buenas noches" },
    { en: "Yes", fr: "Oui", de: "Ja", no: "Ja", es: "Sí" },
    { en: "No", fr: "Non", de: "Nein", no: "Nei", es: "No" },
    { en: "Goodbye", fr: "Au revoir", de: "Auf Wiedersehen", no: "Ha det bra", es: "Adiós" },
    { en: "How are you?", fr: "Comment allez-vous ?", de: "Wie geht es dir?", no: "Hvordan har du det?", es: "¿Cómo estás?" },
    { en: "I am fine", fr: "Je vais bien", de: "Mir geht es gut", no: "Jeg har det bra", es: "Estoy bien" },
    { en: "Excuse me", fr: "Excusez-moi", de: "Entschuldigung", no: "Unnskyld meg", es: "Disculpe" },
    { en: "I'm sorry", fr: "Je suis désolé", de: "Es tut mir leid", no: "Beklager", es: "Lo siento" },
    { en: "What is your name?", fr: "Comment vous appelez-vous ?", de: "Wie heißt du?", no: "Hva heter du?", es: "¿Cómo te llamas?" },
    { en: "My name is...", fr: "Je m'appelle...", de: "Ich heiße...", no: "Jeg heter...", es: "Me llamo..." },
    { en: "Where is the bathroom?", fr: "Où sont les toilettes ?", de: "Wo ist die Toilette?", no: "Hvor er toalettet?", es: "¿Dónde está el baño?" },
    { en: "How much is this?", fr: "Combien ça coûte ?", de: "Wie viel kostet das?", no: "Hvor mye koster dette?", es: "¿Cuánto cuesta esto?" },
    { en: "I don't understand", fr: "Je ne comprends pas", de: "Ich verstehe nicht", no: "Jeg forstår ikke", es: "No entiendo" },
    { en: "Do you speak English?", fr: "Parlez-vous anglais ?", de: "Sprechen Sie Englisch?", no: "Snakker du engelsk?", es: "¿Hablas inglés?" },
    { en: "I love you", fr: "Je t'aime", de: "Ich liebe dich", no: "Jeg elsker deg", es: "Te amo" },
    { en: "Water", fr: "Eau", de: "Wasser", no: "Vann", es: "Agua" },
    { en: "Food", fr: "Nourriture", de: "Essen", no: "Mat", es: "Comida" },
    { en: "Delicious", fr: "Délicieux", de: "Lecker", no: "Deilig", es: "Delicioso" },
    { en: "Beautiful", fr: "Beau / Belle", de: "Schön", no: "Vakker", es: "Hermoso" },
    { en: "Friend", fr: "Ami", de: "Freund", no: "Venn", es: "Amigo" },
    { en: "Family", fr: "Famille", de: "Familie", no: "Familie", es: "Familia" },
    { en: "Today", fr: "Aujourd'hui", de: "Heute", no: "I dag", es: "Hoy" },
    { en: "Tomorrow", fr: "Demain", de: "Morgen", no: "I morgen", es: "Mañana" },
    { en: "Always", fr: "Toujours", de: "Immer", no: "Alltid", es: "Siempre" },
    { en: "Happy", fr: "Heureux", de: "Glücklich", no: "Lykkelig", es: "Feliz" },
    { en: "Tired", fr: "Fatigué", de: "Müde", no: "Trøtt", es: "Cansado" },
    { en: "Let's go!", fr: "Allons-y !", de: "Lass uns gehen!", no: "La oss gå!", es: "¡Vamos!" }
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
        es: 'es-ES'
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
    <div class="h-full w-full flex flex-col gap-3 mt-8">
        <div class="flex items-center justify-between px-2">
            <h3 class="text-sm font-black tracking-widest uppercase text-black/40 dark:text-white/40 flex items-center gap-2">
                <Globe class="h-4 w-4" />
                Word of the Day
            </h3>
            <Button variant="ghost" size="icon" class="h-6 w-6 rounded-full opacity-50 hover:bg-black/5 dark:hover:bg-white/10 hover:opacity-100" @click="isSettingsOpen = true">
                <Settings class="h-4 w-4" />
            </Button>
        </div>

        <div class="flex-1 flex flex-col items-center justify-center p-4">
            <div class="w-full max-w-sm rounded-[2rem] border border-slate-200 bg-white/50 dark:border-white/5 dark:bg-white/5 p-8 shadow-sm flex flex-col items-center gap-8">
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
                    <template v-for="lang in availableLanguages" :key="lang.id">
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

                <div class="grid gap-4 py-4">
                    <div 
                        v-for="lang in availableLanguages" 
                        :key="lang.id"
                        class="flex items-center justify-between p-4 rounded-[1.5rem] bg-white/40 dark:bg-white/5 border border-white/20 transition-colors"
                        :class="{ 'ring-2 ring-primary bg-white/60 dark:bg-white/10': enabledLanguages.includes(lang.id) }"
                        @click="toggleLanguage(lang.id)"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ lang.flag }}</span>
                            <Label class="text-base font-bold cursor-pointer">{{ lang.name }}</Label>
                        </div>
                        <Switch :checked="enabledLanguages.includes(lang.id)" />
                    </div>
                </div>

                <DialogFooter>
                    <Button class="rounded-xl font-bold w-full" @click="isSettingsOpen = false">Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
