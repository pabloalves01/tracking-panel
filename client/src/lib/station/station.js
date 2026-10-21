import { api } from '../api'

export function getStation() {
    return api.get('/station/me').then((response) => response.data.data)
}
