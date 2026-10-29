import { api } from '../api'

/**
 * Catálogos globais da frota, mantidos pela LogIV. Mudam raramente:
 * cada um é buscado uma vez por sessão da página.
 */

const cache = new Map()

function cached(path) {
    if (!cache.has(path)) {
        cache.set(
            path,
            api.get(path).then((response) => response.data.data).catch((error) => {
                cache.delete(path)
                throw error
            })
        )
    }

    return cache.get(path)
}

export const getVehicleTypes = () => cached('/fleet/vehicle-types')
export const getBodyTypes = () => cached('/fleet/body-types')
export const getDeviceModels = () => cached('/fleet/device-models')

/** Marcas do grupo: as da LogIV e as que a empresa acrescentou. */
export function getVehicleBrands(kind) {
    return api.get('/fleet/vehicle-brands', { params: { kind } }).then((response) => response.data.data)
}

/** Acrescenta a marca, ou devolve a que já existe com o mesmo nome. */
export function createVehicleBrand(kind, name) {
    return api.post('/fleet/vehicle-brands', { kind, name }).then((response) => response.data.data)
}

export function getVehicleModels(brandId) {
    return api.get(`/fleet/vehicle-brands/${brandId}/models`).then((response) => response.data.data)
}

export function createVehicleModel(brandId, name) {
    return api.post(`/fleet/vehicle-brands/${brandId}/models`, { name }).then((response) => response.data.data)
}
