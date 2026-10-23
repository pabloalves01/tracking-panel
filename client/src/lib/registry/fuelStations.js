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
 * Delete a request to link a company to a fuel station.
 * @param {*} uuid fuel station uuid
 * @returns (string) success message
 */
export function deleteCompanyFuelStation(uuid) {
    return api.delete(`/registry/fuel-stations/${uuid}`).then((response) => response.data)
}

/**
 * Resend a request to link a company to a fuel station.
 * @param {*} uuid fuel station uuid
 * @returns (string) success message
 */
export function resendCompanyFuelStationRequest(uuid) {
    return api.post(`/registry/fuel-stations/${uuid}/resend-request`).then((response) => response.data)
}

/**
 * Disconnect an active link between a company and a fuel station.
 * @param {*} uuid fuel station uuid
 * @returns (string) success message
 */
export function disconnectCompanyFuelStation(uuid) {
    return api.post(`/registry/fuel-stations/${uuid}/disconnect`).then((response) => response.data)
}
