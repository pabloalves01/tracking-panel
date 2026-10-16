import { api } from '../api'

async function getShipPerimeters(shipOperationId = 1) {
    return api.get(`/ship-operation-perimeters/${shipOperationId}`)
        .then(response => response.data)
        .catch(error => {
            console.error(error)
            throw error
        })
}

async function createShipOperationPerimeter(shipOperationId, perimeter) {
    return api.post('/ship-operation-perimeters', {
        ship_operation_id: shipOperationId,
        perimeter: perimeter,
    })
}

export { createShipOperationPerimeter, getShipPerimeters }

