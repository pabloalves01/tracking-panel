import { api } from '../api'

export function getEntities() {
    return api.get('/registry/entities').then((response) => response.data.data)
}

export function getEntity(uuid) {
    return api.get(`/registry/entities/${uuid}`).then((response) => response.data)
}

export function updateEntity(uuid, entity) {
    return api.put(`/registry/entities/${uuid}`, entity).then((response) => response.data)
}

export function getEntityByDocument(document) {
    return api.get(`/registry/entities/by-document/${document}`).then((response) => response.data.data)
}

export default {
    getEntities,
    getEntity,
    updateEntity,
    getEntityByDocument,
}
