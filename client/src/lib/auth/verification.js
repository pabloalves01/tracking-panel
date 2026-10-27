import { api } from '../api'
import { apiMessage } from '../notify'

/**
 * Erro das telas de código. O 429 chega em português, no texto da página.
 */
export function verificationError(error, fallback) {
    const message = apiMessage(error, fallback)

    if (error?.response?.status !== 429) {
        return message
    }

    if (typeof message === 'string' && message.trim() && !/too many attempts/i.test(message)) {
        return message
    }

    const seconds = Number(error?.response?.data?.retry_after || 0)
    const minutes = Math.max(1, Math.ceil(seconds / 60))

    return seconds > 0
        ? `Muitas tentativas. Tente novamente em ${minutes} min.`
        : 'Muitas tentativas. Tente novamente em instantes.'
}

export function sendVerificationCode(payload) {
    return api.post('/auth/verification-codes', payload).then((response) => response.data)
}

export function forgotPassword(email) {
    return api.post('/auth/password/forgot', { email }).then((response) => response.data)
}

export function resetPassword(payload) {
    return api.post('/auth/password/reset', payload).then((response) => response.data)
}
