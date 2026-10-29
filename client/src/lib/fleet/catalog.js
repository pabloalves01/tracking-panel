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
