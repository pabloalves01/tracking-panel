import { api } from '../api'

/** O que a tela pública do convite mostra antes de qualquer ação. */
export function previewInvitation(token) {
    return api.get(`/auth/invitations/${token}`).then((response) => response.data.data)
}

export function acceptInvitation(token) {
    return api.post(`/auth/invitations/${token}/accept`).then((response) => response.data)
}

export function declineInvitation(token) {
    return api.post(`/auth/invitations/${token}/decline`).then((response) => response.data)
}
