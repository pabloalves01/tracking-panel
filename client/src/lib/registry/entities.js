import { api } from '../api'

/**
 * O cadastro de entidades inteiro. Antes eram oito módulos quase idênticos,
 * um por papel; agora o papel é um parâmetro.
 */

/** Todas as entidades, de qualquer papel. */
export function getAllEntities(params = {}) {
    return api.get('/registry/entities', { params }).then((response) => response.data)
}

export function getEntities(role, params = {}) {
    return api
        .get(`/registry/entities/${role}`, { params })
        .then((response) => response.data)
}

export function getEntity(role, uuid) {
    return api
        .get(`/registry/entities/${role}/${uuid}`)
        .then((response) => response.data.data)
}

export function createEntity(role, payload) {
    return api.post(`/registry/entities/${role}`, payload).then((response) => response.data)
}

export function updateEntity(role, uuid, payload) {
    return api
        .put(`/registry/entities/${role}/${uuid}`, payload)
        .then((response) => response.data)
}

export function activateEntityRole(role, uuid) {
    return api
        .post(`/registry/entities/${role}/${uuid}/activate`)
        .then((response) => response.data)
}

export function deactivateEntityRole(role, uuid) {
    return api
        .post(`/registry/entities/${role}/${uuid}/deactivate`)
        .then((response) => response.data)
}

export function removeEntityRole(role, uuid) {
    return api.delete(`/registry/entities/${role}/${uuid}`).then((response) => response.data)
}

/**
 * Procura pelo documento, para o formulário avisar que a pessoa já existe
 * antes de o usuário digitar tudo de novo. Devolve `null` quando não há.
 */
export function getEntityByDocument(document) {
    return api
        .get(`/registry/entities/by-document/${document}`)
        .then((response) => response.data.data ?? null)
}
