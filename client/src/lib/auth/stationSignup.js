import { api } from '../api'

export function lookupStationCnpj(cnpj) {
    return api.get(`/auth/station-signup/cnpj/${cnpj}`).then((response) => response.data)
}

export function storeStationSignup(payload) {
    return api.post('/auth/station-signup', payload).then((response) => response.data)
}
