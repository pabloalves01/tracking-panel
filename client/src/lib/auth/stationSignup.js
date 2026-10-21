import { api } from '../api'

export function lookupStationCnpj(cnpj) {
    return api.get(`/auth/station-signup/cnpj/${cnpj}`).then((response) => response.data)
}

export function checkStationSignupEmail(payload) {
    return api.post('/auth/station-signup/check-email', payload).then((response) => response.data)
}

export function startStationSignup(payload) {
    return api.post('/auth/station-signup', payload).then((response) => response.data)
}

export function resendStationSignupCode(uuid) {
    return api.post(`/auth/station-signup/${uuid}/resend`).then((response) => response.data)
}

export function verifyStationSignup(uuid, code) {
    return api.post(`/auth/station-signup/${uuid}/verify`, { code }).then((response) => response.data)
}
