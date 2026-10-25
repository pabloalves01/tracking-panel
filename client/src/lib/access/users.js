import { api } from '../api'

/**
 * Usuários e convites do contexto do token.
 * `scope` é 'company' (painel da empresa) ou 'station' (painel do posto).
 */
export function getMembers(scope) {
    return api.get(`/${scope}/users`).then((response) => response.data.data)
}

export function updateMemberRole(scope, uuid, role) {
    return api.patch(`/${scope}/users/${uuid}`, { role }).then((response) => response.data)
}

export function removeMember(scope, uuid) {
    return api.delete(`/${scope}/users/${uuid}`).then((response) => response.data)
}

export function getInvitations(scope) {
    return api.get(`/${scope}/invitations`).then((response) => response.data.data)
}

/** Devolve `{ data, token }`. O token só aparece aqui e no novo link. */
export function createInvitation(scope, payload) {
    return api.post(`/${scope}/invitations`, payload).then((response) => response.data)
}

/** Devolve `{ data, token }`. O link anterior deixa de funcionar. */
export function renewInvitation(scope, uuid) {
    return api.post(`/${scope}/invitations/${uuid}/renew`).then((response) => response.data)
}

export function cancelInvitation(scope, uuid) {
    return api.delete(`/${scope}/invitations/${uuid}`).then((response) => response.data)
}
