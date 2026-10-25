import { api } from '../api'

export function getInvitation(token) {
    return api.get(`/auth/invitations/${token}`).then((response) => response.data.data)
}

export function registerInvitation(token, payload) {
    return api.post(`/auth/invitations/${token}/register`, payload).then((response) => {
        localStorage.setItem('token', response.data.token)
        return response.data
    })
}

export function acceptInvitation(token) {
    return api.post(`/auth/invitations/${token}/accept`).then((response) => response.data)
}

/**
 * Link que o admin copia e envia. O token só aparece na criação e no "novo link".
 */
export function invitationLink(token) {
    return `${window.location.origin}/auth/convite/${token}`
}
