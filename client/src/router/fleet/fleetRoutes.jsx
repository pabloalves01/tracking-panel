import DeviceFormPage from '@/pages/fleet/DeviceFormPage'
import DevicesPage from '@/pages/fleet/DevicesPage'
import FleetPage from '@/pages/fleet/FleetPage'
import VehicleFormPage from '@/pages/fleet/VehicleFormPage'
import VehiclesPage from '@/pages/fleet/VehiclesPage'

/**
 * Veículos e implementos são o mesmo cadastro na API, em telas separadas:
 * as páginas recebem o grupo (`kind`).
 */
export default [
    {
        path: 'frota',
        children: [
            { index: true, element: <FleetPage /> },
            { path: 'veiculos', element: <VehiclesPage key="motor_vehicle" kind="motor_vehicle" /> },
            { path: 'veiculos/novo', element: <VehicleFormPage key="motor_vehicle" kind="motor_vehicle" /> },
            { path: 'veiculos/:uuid/editar', element: <VehicleFormPage key="motor_vehicle" kind="motor_vehicle" /> },
            { path: 'implementos', element: <VehiclesPage key="trailer" kind="trailer" /> },
            { path: 'implementos/novo', element: <VehicleFormPage key="trailer" kind="trailer" /> },
            { path: 'implementos/:uuid/editar', element: <VehicleFormPage key="trailer" kind="trailer" /> },
            { path: 'rastreadores', element: <DevicesPage /> },
            { path: 'rastreadores/novo', element: <DeviceFormPage /> },
            { path: 'rastreadores/:uuid/editar', element: <DeviceFormPage /> },
        ],
    },
]
