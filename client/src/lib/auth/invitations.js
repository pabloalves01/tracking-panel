import { api } from '../api'

export function previewInvitation(token) {
    return api.get(`/auth/invitations/${token}`).then((response) => response.data)
}

export function registerInvitation(token, payload) {
    return api.post(`/auth/invitations/${token}/register`, payload).then((response) => {
        if (response.data.token) {
            localStorage.setItem('token', response.data.token)
        }
        return response.data
    })
}

export function acceptInvitation(token) {
    return api.post(`/auth/invitations/${token}/accept`).then((response) => response.data)
}

export function declineInvitation(token) {
    return api.post(`/auth/invitations/${token}/decline`).then((response) => response.data)
}
