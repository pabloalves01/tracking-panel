import { api } from '../api'

export function getCustomers() {
    return api.get('/registry/customers').then((response) => response.data.data)
}

export function createCustomer(customer) {
    return api.post('/registry/customers', customer).then((response) => response.data)
}

export function getCustomer(uuid) {
    return api.get(`/registry/customers/${uuid}`).then((response) => response.data)
}

export function updateCustomer(uuid, customer) {
    return api.put(`/registry/customers/${uuid}`, customer).then((response) => response.data)
}

export function deactivateCustomer(uuid) {
    return api.post(`/registry/customers/${uuid}/deactivate`).then((response) => response.data)
}

export function activateCustomer(uuid) {
    return api.post(`/registry/customers/${uuid}/activate`).then((response) => response.data)
}

export default { getCustomers, createCustomer, getCustomer, updateCustomer, deactivateCustomer, activateCustomer }
