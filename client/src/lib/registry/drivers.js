import { api } from '../api'

export function getDrivers() {
    return api.get('/registry/drivers').then((response) => response.data.data)
}

export function createDriver(driver) {
    return api.post('/registry/drivers', driver).then((response) => response.data)
}

export function getDriver(uuid) {
    return api.get(`/registry/drivers/${uuid}`).then((response) => response.data)
}

export function updateDriver(uuid, driver) {
    return api.put(`/registry/drivers/${uuid}`, driver).then((response) => response.data)
}

export function deactivateDriver(uuid) {
    return api.post(`/registry/drivers/${uuid}/deactivate`).then((response) => response.data)
}

export function activateDriver(uuid) {
    return api.post(`/registry/drivers/${uuid}/activate`).then((response) => response.data)
}

export default { getDrivers, createDriver, getDriver, updateDriver, deactivateDriver, activateDriver }
