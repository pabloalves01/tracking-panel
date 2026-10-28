import { api } from '../api'

/** Consulta o CNPJ. Vem normalizado no vocabulário de organizations. */
export function lookupStationCnpj(cnpj) {
    return api
        .get(`/auth/station-signup/cnpj/${cnpj}`)
        .then((response) => response.data.data)
}

export function storeStationSignup(payload) {
    return api.post('/auth/station-signup', payload).then((response) => {
        if (response.data.token) {
            localStorage.setItem('token', response.data.token)
        }
        return response.data
    })
}

export function storeStationForUser(cnpj) {
    return api.post('/auth/stations', { cnpj }).then((response) => response.data)
}
