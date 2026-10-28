import { api } from '../api'

/** Consulta o CNPJ. Vem normalizado no vocabulário de organizations. */
export function lookupFuelStationCnpj(cnpj) {
    return api
        .get(`/auth/fuel-station-signup/cnpj/${cnpj}`)
        .then((response) => response.data.data)
}

export function storeFuelStationSignup(payload) {
    return api.post('/auth/fuel-station-signup', payload).then((response) => {
        if (response.data.token) {
            localStorage.setItem('token', response.data.token)
        }
        return response.data
    })
}

export function storeFuelStationForUser(cnpj) {
    return api.post('/auth/fuel-stations', { cnpj }).then((response) => response.data)
}
