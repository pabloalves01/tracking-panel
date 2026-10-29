import { api } from '../api'

/**
 * Rastreadores. Existem sem veículo; instalar, retirar e transferir têm
 * rota própria.
 */

const endpoint = '/fleet/devices'

export function getDevices(params = {}) {
    return api.get(endpoint, { params }).then((response) => response.data)
}

export function getDevice(uuid) {
    return api.get(`${endpoint}/${uuid}`).then((response) => response.data.data)
}

export function createDevice(payload) {
    return api.post(endpoint, payload).then((response) => response.data)
}

export function updateDevice(uuid, payload) {
    return api.put(`${endpoint}/${uuid}`, payload).then((response) => response.data)
}

export function activateDevice(uuid) {
    return api.post(`${endpoint}/${uuid}/activate`).then((response) => response.data)
}

export function deactivateDevice(uuid) {
    return api.post(`${endpoint}/${uuid}/deactivate`).then((response) => response.data)
}

export function removeDevice(uuid) {
    return api.delete(`${endpoint}/${uuid}`).then((response) => response.data)
}

/**
 * `transfer` tira o rastreador do veículo em que está; sem ele, a API
 * recusa rastreador já instalado.
 */
export function installDevice(uuid, { vehicle, role = null, transfer = false }) {
    return api
        .post(`${endpoint}/${uuid}/install`, { vehicle, role, transfer })
        .then((response) => response.data)
}

export function uninstallDevice(uuid) {
    return api.post(`${endpoint}/${uuid}/uninstall`).then((response) => response.data)
}

/** Onde o rastreador esteve, do mais recente. */
export function getDeviceInstallations(uuid) {
    return api.get(`${endpoint}/${uuid}/installations`).then((response) => response.data.data)
}
