import { api } from '../api'

/** As organizações, para o admin do sistema. */

export function getOrganizations(params = {}) {
    return api.get('/system/organizations', { params }).then((response) => response.data)
}

export function getOrganization(uuid) {
    return api.get(`/system/organizations/${uuid}`).then((response) => response.data.data)
}

export function createOrganization(payload) {
    return api.post('/system/organizations', payload).then((response) => response.data)
}

export function updateOrganization(uuid, payload) {
    return api.put(`/system/organizations/${uuid}`, payload).then((response) => response.data)
}

export function activateOrganization(uuid) {
    return api.post(`/system/organizations/${uuid}/activate`).then((response) => response.data)
}

export function deactivateOrganization(uuid) {
    return api.post(`/system/organizations/${uuid}/deactivate`).then((response) => response.data)
}
