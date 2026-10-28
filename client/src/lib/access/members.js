import { api } from '../api'

/**
 * Membros e convites da organização ativa.
 *
 * Um módulo em vez de três: as mesmas rotas existiam para empresa, posto e
 * sistema.
 */

export function getMembers() {
    return api.get('/organization/members').then((response) => response.data.data)
}

export function suspendMember(uuid) {
    return api.post(`/organization/members/${uuid}/suspend`).then((response) => response.data)
}

export function reactivateMember(uuid) {
    return api.post(`/organization/members/${uuid}/reactivate`).then((response) => response.data)
}

export function removeMember(uuid) {
    return api.delete(`/organization/members/${uuid}`).then((response) => response.data)
}

export function getInvitations() {
    return api.get('/organization/invitations').then((response) => response.data.data)
}

export function createInvitation(email) {
    return api.post('/organization/invitations', { email }).then((response) => response.data)
}

export function resendInvitation(uuid) {
    return api.post(`/organization/invitations/${uuid}/resend`).then((response) => response.data)
}

export function revokeInvitation(uuid) {
    return api.delete(`/organization/invitations/${uuid}`).then((response) => response.data)
}
