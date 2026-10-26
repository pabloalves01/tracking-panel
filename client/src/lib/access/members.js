import { api } from '../api'

/**
 * Usuários e convites de um contexto.
 * `base` é `/company`, `/station` ou `/system/companies/{uuid}`.
 */
export function memberApi(base) {
    return {
        list() {
            return api.get(`${base}/users`).then((response) => response.data.data)
        },
        updateRole(uuid, role) {
            return api.patch(`${base}/users/${uuid}`, { role }).then((response) => response.data)
        },
        remove(uuid) {
            return api.delete(`${base}/users/${uuid}`).then((response) => response.data)
        },
        invitations() {
            return api.get(`${base}/invitations`).then((response) => response.data.data)
        },
        invite(payload) {
            return api.post(`${base}/invitations`, payload).then((response) => response.data.data)
        },
        resend(uuid) {
            return api.post(`${base}/invitations/${uuid}/resend`).then((response) => response.data.data)
        },
        revoke(uuid) {
            return api.delete(`${base}/invitations/${uuid}`).then((response) => response.data)
        },
    }
}
