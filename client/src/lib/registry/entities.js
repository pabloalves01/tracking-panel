import { api } from '../api'
import { ENTITY_ROLES } from './roles'

/**
 * O cadastro de entidades. Cada papel tem a sua rota na API
 * (`/registry/customers`, `/registry/drivers`…); as funções recebem o papel
 * e acham a rota em `roles.js`.
 *
 * O que vem de um papel é o registro dele — `uuid` e `status` do papel, a
 * pessoa em `entity` e as colunas próprias no mesmo nível.
 */

function endpoint(role) {
    return ENTITY_ROLES[role].endpoint
}

export function getEntities(role, params = {}) {
    return api.get(endpoint(role), { params }).then((response) => response.data)
}

export function getEntity(role, uuid) {
    return api.get(`${endpoint(role)}/${uuid}`).then((response) => response.data.data)
}

export function createEntity(role, payload) {
    return api.post(endpoint(role), payload).then((response) => response.data)
}

export function updateEntity(role, uuid, payload) {
    return api.put(`${endpoint(role)}/${uuid}`, payload).then((response) => response.data)
}

export function activateEntityRole(role, uuid) {
    return api.post(`${endpoint(role)}/${uuid}/activate`).then((response) => response.data)
}

export function deactivateEntityRole(role, uuid) {
    return api.post(`${endpoint(role)}/${uuid}/deactivate`).then((response) => response.data)
}

export function removeEntityRole(role, uuid) {
    return api.delete(`${endpoint(role)}/${uuid}`).then((response) => response.data)
}

/** Todas as pessoas, de qualquer papel. */
export function getAllEntities(params = {}) {
    return api.get('/registry/entities', { params }).then((response) => response.data)
}

/** Exclui a pessoa de todos os cadastros. */
export function removeEntity(uuid) {
    return api.delete(`/registry/entities/${uuid}`).then((response) => response.data)
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
