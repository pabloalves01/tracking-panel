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

export function acceptPendingInvitation(uuid) {
    return api.post(`/auth/pending-invitations/${uuid}/accept`).then((response) => response.data)
}

export function declinePendingInvitation(uuid) {
    return api.post(`/auth/pending-invitations/${uuid}/decline`).then((response) => response.data)
}
