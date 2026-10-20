import { api } from '../api'

export function getSuppliers() {
    return api.get('/registry/suppliers').then((response) => response.data.data)
}

export function createSupplier(supplier) {
    return api.post('/registry/suppliers', supplier).then((response) => response.data)
}

export function getSupplier(uuid) {
    return api.get(`/registry/suppliers/${uuid}`).then((response) => response.data)
}

export function updateSupplier(uuid, supplier) {
    return api.put(`/registry/suppliers/${uuid}`, supplier).then((response) => response.data)
}

export function deleteSupplier(uuid) {
    return api.delete(`/registry/suppliers/${uuid}`).then((response) => response.data)
}

export default { getSuppliers, createSupplier, getSupplier, updateSupplier, deleteSupplier }