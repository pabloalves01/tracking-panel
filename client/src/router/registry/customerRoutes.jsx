import CustomerFormPage from '@/pages/registry/CustomerFormPage'
import CustomersPage from '@/pages/registry/CustomersPage'

export default [
    { path: 'clientes', element: <CustomersPage /> },
    { path: 'clientes/novo', element: <CustomerFormPage /> },
    { path: 'clientes/:uuid/editar', element: <CustomerFormPage /> },
]
