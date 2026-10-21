import CarrierFormPage from '@/pages/registry/CarrierFormPage'
import CarriersPage from '@/pages/registry/CarriersPage'

export default [
    { path: 'transportadoras', element: <CarriersPage /> },
    { path: 'transportadoras/novo', element: <CarrierFormPage /> },
    { path: 'transportadoras/:uuid/editar', element: <CarrierFormPage /> },
]
