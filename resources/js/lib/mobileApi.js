import axios from 'axios'

const TOKEN_KEY = 'family_hub_mobile_token'
const DEVICE_KEY = 'family_hub_mobile_device'

export const UNAUTHORIZED_EVENT = 'family-hub:unauthorized'

export function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY) || ''
    } catch {
        return ''
    }
}

export function setToken(token) {
    try {
        if (token) localStorage.setItem(TOKEN_KEY, token)
        else localStorage.removeItem(TOKEN_KEY)
    } catch {
        /* storage unavailable */
    }
}

export function getStoredDevice() {
    try {
        const raw = localStorage.getItem(DEVICE_KEY)
        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

export function setStoredDevice(device) {
    try {
        if (device) localStorage.setItem(DEVICE_KEY, JSON.stringify(device))
        else localStorage.removeItem(DEVICE_KEY)
    } catch {
        /* storage unavailable */
    }
}

export function clearSession() {
    setToken(null)
    setStoredDevice(null)
}

export const api = axios.create({
    baseURL: '/api/mobile',
    headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
    const token = getToken()
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            clearSession()
            window.dispatchEvent(new Event(UNAUTHORIZED_EVENT))
        }
        return Promise.reject(error)
    },
)

export async function pair(code, deviceName) {
    const { data } = await axios.post(
        '/api/mobile/pair',
        { code, device_name: deviceName },
        { headers: { Accept: 'application/json' } },
    )
    return data
}

export async function fetchBootstrap() {
    const { data } = await api.get('/bootstrap')
    return data
}

export async function unpair() {
    try {
        await api.delete('/device')
    } finally {
        clearSession()
    }
}

export const choreApi = {
    list: (profile, date) =>
        api.get('/chores', { params: { profile, date } }).then((r) => r.data),
    create: (payload) => api.post('/chores', payload).then((r) => r.data),
    update: (id, payload) =>
        api.put(`/chores/${id}`, payload).then((r) => r.data),
    remove: (id) => api.delete(`/chores/${id}`),
    toggle: (id, { date, subtaskIds }) =>
        api
            .post(`/chores/${id}/toggle`, {
                date,
                subtask_ids: subtaskIds,
            })
            .then((r) => r.data),
    approvals: () => api.get('/chores/approvals').then((r) => r.data),
    processApproval: (id, action, awardedValue) =>
        api
            .post(`/chores/approvals/${id}`, {
                action,
                awarded_value: awardedValue ?? null,
            })
            .then((r) => r.data),
}

export const labelApi = {
    list: () => api.get('/labels').then((r) => r.data),
    create: (payload) => api.post('/labels', payload).then((r) => r.data),
    update: (id, payload) => api.put(`/labels/${id}`, payload).then((r) => r.data),
    remove: (id) => api.delete(`/labels/${id}`),
}

export const rewardApi = {
    bank: (profile) =>
        api.get('/rewards/bank', { params: { profile } }).then((r) => r.data),
    redeem: (payload) => api.post('/rewards/redeem', payload).then((r) => r.data),
}
