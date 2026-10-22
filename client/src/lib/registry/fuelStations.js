import { api } from '../api'

export function getCompanyFuelStations() {
    return api.get('/registry/fuel-stations').then((response) => response.data.data)
}

export function lookupCompanyFuelStation(cnpj) {
    return api.get('/registry/fuel-stations/by-cnpj', { params: { cnpj } }).then((response) => response.data.data)
}

export function storeCompanyFuelStation(cnpj) {
    return api.post('/registry/fuel-stations', { cnpj }).then((response) => response.data)
}

export function deleteCompanyFuelStation(cnpj) {
    return api.delete(`/registry/fuel-stations/${cnpj}`).then((response) => response.data)
}
