import { api } from '../api'

/**
 * Catálogo de estados e cidades do IBGE.
 * `id` é o valor gravado em `state_id` e `city_id`.
 */
export function getStates() {
    return api.get('/locations/states').then((response) => response.data.data)
}

export function getCities(stateId) {
    return api.get(`/locations/states/${stateId}/cities`).then((response) => response.data.data)
}
