import { api } from '../api'

export function lookupStationCnpj(cnpj) {
    return api.get(`/auth/station-signup/cnpj/${cnpj}`).then((response) => response.data)
}

/** Conta nova e posto. Guarda o token, que já vem no posto. */
export function storeStationSignup(payload) {
    return api.post('/auth/station-signup', payload).then((response) => {
        localStorage.setItem('token', response.data.token)
        return response.data
    })
}

/** Posto na conta logada. O token desta sessão passa a ser do posto. */
export function storeStationForUser(cnpj) {
    return api.post('/auth/stations', { cnpj }).then((response) => response.data)
}
