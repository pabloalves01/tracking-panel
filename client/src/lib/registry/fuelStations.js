import { api } from '../api'

export function getFuelStations() {
    return api.get('/registry/fuel-stations').then((response) => response.data.data)
}

export function createFuelStation(fuelStation) {
    return api.post('/registry/fuel-stations', fuelStation).then((response) => response.data)
}

export function getFuelStation(uuid) {
    return api.get(`/registry/fuel-stations/${uuid}`).then((response) => response.data)
}

export function updateFuelStation(uuid, fuelStation) {
    return api.put(`/registry/fuel-stations/${uuid}`, fuelStation).then((response) => response.data)
}

export function deactivateFuelStation(uuid) {
    return api.post(`/registry/fuel-stations/${uuid}/deactivate`).then((response) => response.data)
}

export function activateFuelStation(uuid) {
    return api.post(`/registry/fuel-stations/${uuid}/activate`).then((response) => response.data)
}

export default { getFuelStations, createFuelStation, getFuelStation, updateFuelStation, deactivateFuelStation, activateFuelStation }