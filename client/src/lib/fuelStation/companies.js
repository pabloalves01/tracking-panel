import { api } from '../api'

export function getFuelStationCompanies() {
    return api.get('/fuel-station/companies').then((response) => response.data.data)
}

export function acceptFuelStationCompany(uuid) {
    return api.post(`/fuel-station/companies/${uuid}/accept`).then((response) => response.data)
}

export function rejectFuelStationCompany(uuid) {
    return api.post(`/fuel-station/companies/${uuid}/reject`).then((response) => response.data)
}

export function disconnectFuelStationCompany(uuid) {
    return api.post(`/fuel-station/companies/${uuid}/disconnect`).then((response) => response.data)
}
