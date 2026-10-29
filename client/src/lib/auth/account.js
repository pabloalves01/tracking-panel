import { api } from '../api'

/**
 * Criar conta, e só conta. Posto e convite vêm depois, já com a sessão.
 *
 * O código é sempre enviado, exista a conta ou não; `checkAccountCode` é que
 * diz, depois de o código conferir, se a pessoa entra com a senha atual ou
 * cria uma.
 */

export function sendAccountCode(email) {
    return api
        .post('/auth/verification-codes', { email, purpose: 'account_register' })
        .then((response) => response.data)
}

/** `{ account_exists }` — só responde a quem digitou o código certo. */
export function checkAccountCode(email, code) {
    return api.post('/auth/register/check', { email, code }).then((response) => response.data)
}

/** Cria a conta e já entra. A resposta tem a forma da do login. */
export function registerAccount(payload) {
    return api.post('/auth/register', payload).then((response) => {
        localStorage.setItem('token', response.data.token)
        return response.data
    })
}

/** A conta passou a existir entre a conferência do código e o envio. */
export function isAccountExistsConflict(error) {
    return error?.response?.status === 409 && error?.response?.data?.code === 'account_exists'
}
