import DeviceFormPage from '@/pages/fleet/DeviceFormPage'
import DevicesPage from '@/pages/fleet/DevicesPage'
import FleetPage from '@/pages/fleet/FleetPage'
import VehicleFormPage from '@/pages/fleet/VehicleFormPage'
import VehiclesPage from '@/pages/fleet/VehiclesPage'

export default [
    {
        path: 'frota',
        children: [
            { index: true, element: <FleetPage /> },
            { path: 'veiculos', element: <VehiclesPage /> },
            { path: 'veiculos/novo', element: <VehicleFormPage /> },
            { path: 'veiculos/:uuid/editar', element: <VehicleFormPage /> },
            { path: 'rastreadores', element: <DevicesPage /> },
            { path: 'rastreadores/novo', element: <DeviceFormPage /> },
            { path: 'rastreadores/:uuid/editar', element: <DeviceFormPage /> },
        ],
    },
]
