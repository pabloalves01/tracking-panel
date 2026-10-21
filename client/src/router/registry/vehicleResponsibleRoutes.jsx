import VehicleResponsibleFormPage from '@/pages/registry/VehicleResponsibleFormPage'
import VehicleResponsiblesPage from '@/pages/registry/VehicleResponsiblesPage'

export default [
    { path: 'responsaveis', element: <VehicleResponsiblesPage /> },
    { path: 'responsaveis/novo', element: <VehicleResponsibleFormPage /> },
    { path: 'responsaveis/:uuid/editar', element: <VehicleResponsibleFormPage /> },
]
