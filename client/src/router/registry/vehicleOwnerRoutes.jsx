import VehicleOwnerFormPage from '@/pages/registry/VehicleOwnerFormPage'
import VehicleOwnersPage from '@/pages/registry/VehicleOwnersPage'

export default [
    { path: 'proprietarios', element: <VehicleOwnersPage /> },
    { path: 'proprietarios/novo', element: <VehicleOwnerFormPage /> },
    { path: 'proprietarios/:uuid/editar', element: <VehicleOwnerFormPage /> },
]
