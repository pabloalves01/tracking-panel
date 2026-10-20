import { api } from '../api'

export function createCompany(company) {
    return api.post('/system/companies', company).then((response) => response.data)
}

export function getCompanies() {
    return api.get('/system/companies').then((response) => response.data.data)
}

export function getCompany(uuid) {
    return api.get(`/system/companies/${uuid}`).then((response) => response.data.data)
}

export function updateCompany(uuid, company) {
    return api.put(`/system/companies/${uuid}`, company).then((response) => response.data)
}

export function deactivateCompany(uuid) {
    return api.post(`/system/companies/${uuid}/deactivate`).then((response) => response.data)
}

export function activateCompany(uuid) {
    return api.post(`/system/companies/${uuid}/activate`).then((response) => response.data)
}

export default {
    createCompany,
    getCompanies,
    getCompany,
    updateCompany,
    deactivateCompany,
    activateCompany
} 
