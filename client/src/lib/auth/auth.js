import { api } from '../api'

/**
 * Entrar, sair e escolher a organização da sessão.
 *
 * Eram três módulos — `auth`, `session` e `stationSession` — com rotas
 * separadas para empresa e posto. A API agora devolve tudo resolvido numa
 * resposta só.
 */

export function login(credentials) {
    return api.post('/auth/login', credentials).then((response) => {
        localStorage.setItem('token', response.data.token)
        return response.data
    })
}

/** Usuário, organização atual, organizações disponíveis e convites pendentes. */
export function me() {
    return api.get('/auth/me').then((response) => response.data)
}

export function getOrganizations() {
    return api.get('/auth/organizations').then((response) => response.data)
}

/**
 * Troca a organização da sessão.
 *
 * Devolve `redirect_to`, então o client navega normalmente em vez de
 * recarregar a página inteira com `location.assign`.
 */
export function selectOrganization(uuid) {
    return api.post('/auth/organization', { uuid }).then((response) => response.data)
}

export function logout() {
    return api.post('/auth/logout').finally(() => {
        localStorage.removeItem('token')
    })
}

export function acceptPendingInvitation(uuid) {
    return api.post(`/auth/pending-invitations/${uuid}/accept`).then((response) => response.data)
}

export function declinePendingInvitation(uuid) {
    return api.post(`/auth/pending-invitations/${uuid}/decline`).then((response) => response.data)
}
