import { api } from '../api'

export function getEntityByDocument(document) {
    return api.get(`/registry/entities/by-document/${document}`).then((response) => response.data.data)
}

export default {
    getEntityByDocument
}