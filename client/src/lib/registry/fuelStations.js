import { api } from '../api'

/**
 * Get all requests to link a company to a fuel station.
 * @returns (array) requests data
 */
export function getCompanyFuelStations() {
    return api.get('/registry/fuel-stations').then((response) => response.data.data)
}

/**
 * Lookup a fuel station by CNPJ.
 * @param {*} cnpj 
 * @returns (object) fuel station data
 */
export function lookupCompanyFuelStation(cnpj) {
    return api.get('/registry/fuel-stations/by-cnpj', { params: { cnpj } }).then((response) => response.data.data)
}

/**
 * Store a request to link a company to a fuel station.
 * @param {*} cnpj 
 * @returns (string) success message
 */
export function storeCompanyFuelStation(cnpj) {
    return api.post('/registry/fuel-stations', { cnpj }).then((response) => response.data)
}

/**
 * Cancela um pedido ainda pendente. O endereço usa o uuid do posto.
 */
export function deleteCompanyFuelStation(stationUuid) {
    return api.delete(`/registry/fuel-stations/${stationUuid}`).then((response) => response.data)
}

/**
 * Encerra um vínculo que já está ativo.
 */
export function disconnectCompanyFuelStation(stationUuid) {
    return api.post(`/registry/fuel-stations/${stationUuid}/disconnect`).then((response) => response.data)
}

/**
 * Reenvia um pedido recusado ou encerrado.
 */
export function resendCompanyFuelStationRequest(stationUuid) {
    return api.post(`/registry/fuel-stations/${stationUuid}/resend-request`).then((response) => response.data)
}