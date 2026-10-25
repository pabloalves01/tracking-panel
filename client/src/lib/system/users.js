import { api } from '../api'

export function lookupUser(email) {
    return api.get('/system/users/lookup', { params: { email } }).then((response) => response.data.data)
}
