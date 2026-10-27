import { api } from '../api'

export function sendVerificationCode(payload) {
    return api.post('/auth/verification-codes', payload).then((response) => response.data)
}

export function forgotPassword(email) {
    return api.post('/auth/password/forgot', { email }).then((response) => response.data)
}

export function resetPassword(payload) {
    return api.post('/auth/password/reset', payload).then((response) => response.data)
}
