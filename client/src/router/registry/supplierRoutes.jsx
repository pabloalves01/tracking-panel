import SupplierFormPage from '@/pages/registry/SupplierFormPage'
import SuppliersPage from '@/pages/registry/SuppliersPage'

export default [
    { path: 'fornecedores', element: <SuppliersPage /> },
    { path: 'fornecedores/novo', element: <SupplierFormPage /> },
    { path: 'fornecedores/:uuid/editar', element: <SupplierFormPage /> },
]
