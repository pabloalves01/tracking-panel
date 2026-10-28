import VehicleFormPage from '@/pages/registry/VehicleFormPage'
import VehiclesPage from '@/pages/registry/VehiclesPage'

export default [
    { path: 'veiculos', element: <VehiclesPage /> },
    { path: 'veiculos/novo', element: <VehicleFormPage /> },
]
