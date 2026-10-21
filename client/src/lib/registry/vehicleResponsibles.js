import { api } from '../api'

export function getVehicleResponsibles() {
    return api.get('/registry/vehicle-responsibles').then((response) => response.data.data)
}

export function createVehicleResponsible(vehicleResponsible) {
    return api.post('/registry/vehicle-responsibles', vehicleResponsible).then((response) => response.data)
}

export function getVehicleResponsible(uuid) {
    return api.get(`/registry/vehicle-responsibles/${uuid}`).then((response) => response.data)
}

export function updateVehicleResponsible(uuid, vehicleResponsible) {
    return api.put(`/registry/vehicle-responsibles/${uuid}`, vehicleResponsible).then((response) => response.data)
}

export function deactivateVehicleResponsible(uuid) {
    return api.post(`/registry/vehicle-responsibles/${uuid}/deactivate`).then((response) => response.data)
}

export function activateVehicleResponsible(uuid) {
    return api.post(`/registry/vehicle-responsibles/${uuid}/activate`).then((response) => response.data)
}

export default { getVehicleResponsibles, createVehicleResponsible, getVehicleResponsible, updateVehicleResponsible, deactivateVehicleResponsible, activateVehicleResponsible }