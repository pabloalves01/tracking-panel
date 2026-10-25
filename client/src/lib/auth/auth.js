import { api } from '../api'

export function login(credentials) {
    return api.post('/auth/login', credentials).then((response) => {
        localStorage.setItem('token', response.data.token)
        return response.data
    })
}

export function me() {
    return api.get('/auth/me').then((response) => response.data.data)
}

export function getAuthCompanies() {
    return api.get('/auth/companies').then((response) => response.data.data)
}

export function selectCompany(uuid) {
    return api.post('/auth/company', { uuid }).then((response) => response.data.data)
}

export function getAuthContexts() {
    return api.get('/auth/contexts').then((response) => response.data)
}

export function selectFuelStation(uuid) {
    return api.post('/auth/station', { uuid }).then((response) => response.data.data)
}

export function logout() {
    return api.post('/auth/logout').finally(() => {
        localStorage.removeItem('token')
    })
}

/**
 * `?redirect=` só vale para caminhos internos: começa com "/" e não com "//".
 */
export function safeRedirect(value) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) {
        return null
    }
    return value
}
