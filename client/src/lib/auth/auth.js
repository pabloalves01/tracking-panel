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

export function logout() {
    return api.post('/auth/logout').finally(() => {
        localStorage.removeItem('token')
    })
}
