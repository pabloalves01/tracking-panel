import { api } from '../api'

/**
 * Veículos — automotor e implemento. O grupo (`kind`) vem do tipo; o
 * formulário manda só `vehicle_type_id`.
 */

const endpoint = '/fleet/vehicles'

export function getVehicles(params = {}) {
    return api.get(endpoint, { params }).then((response) => response.data)
}

export function getVehicle(uuid) {
    return api.get(`${endpoint}/${uuid}`).then((response) => response.data.data)
}

export function createVehicle(payload) {
    return api.post(endpoint, payload).then((response) => response.data)
}

export function updateVehicle(uuid, payload) {
    return api.put(`${endpoint}/${uuid}`, payload).then((response) => response.data)
}

export function activateVehicle(uuid) {
    return api.post(`${endpoint}/${uuid}/activate`).then((response) => response.data)
}

export function deactivateVehicle(uuid) {
    return api.post(`${endpoint}/${uuid}/deactivate`).then((response) => response.data)
}

export function removeVehicle(uuid) {
    return api.delete(`${endpoint}/${uuid}`).then((response) => response.data)
}

/** Todos os engates do veículo, puxando e sendo puxado, do mais recente. */
export function getVehicleCouplings(uuid) {
    return api.get(`${endpoint}/${uuid}/couplings`).then((response) => response.data.data)
}
