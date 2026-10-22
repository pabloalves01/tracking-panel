import { api } from '../api'

export function getStationCompanies() {
    return api.get('/station/companies').then((response) => response.data.data)
}

export function acceptStationCompany(uuid) {
    return api.post(`/station/companies/${uuid}/accept`).then((response) => response.data)
}

export function rejectStationCompany(uuid) {
    return api.post(`/station/companies/${uuid}/reject`).then((response) => response.data)
}
