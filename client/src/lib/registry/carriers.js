import { api } from '../api'

export function getCarriers() {
    return api.get('/registry/carriers').then((response) => response.data.data)
}

export function createCarrier(carrier) {
    return api.post('/registry/carriers', carrier).then((response) => response.data)
}

export function getCarrier(uuid) {
    return api.get(`/registry/carriers/${uuid}`).then((response) => response.data)
}

export function updateCarrier(uuid, carrier) {
    return api.put(`/registry/carriers/${uuid}`, carrier).then((response) => response.data)
}

export function deactivateCarrier(uuid) {
    return api.post(`/registry/carriers/${uuid}/deactivate`).then((response) => response.data)
}

export function activateCarrier(uuid) {
    return api.post(`/registry/carriers/${uuid}/activate`).then((response) => response.data)
}

export default { getCarriers, createCarrier, getCarrier, updateCarrier, deactivateCarrier, activateCarrier }
