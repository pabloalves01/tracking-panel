import { api } from '../api'

/**
 * Os postos conveniados, do lado da empresa.
 *
 * O endereço agora usa o uuid do vínculo, não o do posto: o vínculo é o que
 * tem estado, e é dele que as ações falam.
 */

export function getFuelSupplyLinks() {
    return api.get('/registry/fuel-stations').then((response) => response.data.data)
}

/** Procura um posto pelo CNPJ antes de pedir o convênio. */
export function lookupFuelStation(document) {
    return api
        .get('/registry/fuel-stations/by-document', { params: { document } })
        .then((response) => response.data.data)
}

export function requestFuelSupplyLink(document) {
    return api.post('/registry/fuel-stations', { document }).then((response) => response.data)
}

/** Cancela um pedido que o posto ainda não respondeu. */
export function cancelFuelSupplyLink(uuid) {
    return api.delete(`/registry/fuel-stations/${uuid}`).then((response) => response.data)
}

export function disconnectFuelSupplyLink(uuid) {
    return api.post(`/registry/fuel-stations/${uuid}/disconnect`).then((response) => response.data)
}
