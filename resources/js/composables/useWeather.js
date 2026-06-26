import { ref, computed } from 'vue'
import axios from 'axios'

const weatherData = ref(null)
const alerts = ref([])
const airQuality = ref(null)
const location = ref(null)
const apiKey = ref(null)
const isLoading = ref(true)
const isSyncing = ref(false)
let lastFetchTime = 0

const fetchWeather = async (silent = false) => {
    // Prevent fetching if we just fetched less than 5 minutes ago (unless manual sync)
    if (!silent && Date.now() - lastFetchTime < 300000 && weatherData.value) {
        isLoading.value = false
        return
    }

    if (!silent) isLoading.value = true
    else isSyncing.value = true

    try {
        const devSettings = JSON.parse(localStorage.getItem('developerSettings') || 'null') || {}
        const isTestAlerts = devSettings.masterToggle && devSettings.testWeatherAlerts
        const endpoint = isTestAlerts ? '/api/weather?test_alerts=1' : '/api/weather'
        const response = await axios.get(endpoint)
        weatherData.value = response.data.weather
        alerts.value = response.data.alerts
        airQuality.value = response.data.air_quality
        location.value = response.data.location
        apiKey.value = response.data.apiKey
        lastFetchTime = Date.now()
    } catch (error) {
        console.error('Failed to fetch weather:', error)
    } finally {
        isLoading.value = false
        isSyncing.value = false
    }
}

export function useWeather() {
    return {
        weatherData,
        alerts,
        airQuality,
        location,
        apiKey,
        isLoading,
        isSyncing,
        fetchWeather,
    }
}
