import { api } from '../api'

export function getEmployees() {
    return api.get('/registry/employees').then((response) => response.data.data)
}

export function createEmployee(employee) {
    return api.post('/registry/employees', employee).then((response) => response.data)
}

export function getEmployee(uuid) {
    return api.get(`/registry/employees/${uuid}`).then((response) => response.data)
}

export function updateEmployee(uuid, employee) {
    return api.put(`/registry/employees/${uuid}`, employee).then((response) => response.data)
}

export function deactivateEmployee(uuid) {
    return api.post(`/registry/employees/${uuid}/deactivate`).then((response) => response.data)
}

export function activateEmployee(uuid) {
    return api.post(`/registry/employees/${uuid}/activate`).then((response) => response.data)
}

export default { getEmployees, createEmployee, getEmployee, updateEmployee, deactivateEmployee, activateEmployee }
