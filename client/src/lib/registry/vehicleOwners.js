import { api } from '../api'

export function getVehicleOwners() {
    return api.get('/registry/vehicle-owners').then((response) => response.data.data)
}

export function createVehicleOwner(vehicleOwner) {
    return api.post('/registry/vehicle-owners', vehicleOwner).then((response) => response.data)
}

export function getVehicleOwner(uuid) {
    return api.get(`/registry/vehicle-owners/${uuid}`).then((response) => response.data)
}

export function updateVehicleOwner(uuid, vehicleOwner) {
    return api.put(`/registry/vehicle-owners/${uuid}`, vehicleOwner).then((response) => response.data)
}

export function deactivateVehicleOwner(uuid) {
    return api.post(`/registry/vehicle-owners/${uuid}/deactivate`).then((response) => response.data)
}

export function activateVehicleOwner(uuid) {
    return api.post(`/registry/vehicle-owners/${uuid}/activate`).then((response) => response.data)
}

export default { getVehicleOwners, createVehicleOwner, getVehicleOwner, updateVehicleOwner, deactivateVehicleOwner, activateVehicleOwner }