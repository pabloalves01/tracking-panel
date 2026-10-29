import { api } from '../api'

/** Consulta o CNPJ. Vem normalizado no vocabulário de organizations. */
export function lookupFuelStationCnpj(cnpj) {
    return api
        .get(`/auth/fuel-station-signup/cnpj/${cnpj}`)
        .then((response) => response.data.data)
}

/** Cria o posto na conta logada. Quem não tem conta passa pelo AccountGate antes. */
export function storeFuelStationForUser(cnpj) {
    return api.post('/auth/fuel-stations', { cnpj }).then((response) => response.data)
}
