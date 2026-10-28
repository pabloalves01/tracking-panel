import EntityPage from '@/pages/registry/EntityPage'
import FleetPage from '@/pages/registry/FleetPage'
import RegistryPage from '@/pages/registry/RegistryPage'

import entityRoutes from './entityRoutes'
import fuelStationRoutes from './fuelStationRoutes'
import vehicleRoutes from './vehicleRoutes'

export default [
    {
        path: 'cadastros',
        children: [
            { index: true, element: <RegistryPage /> },
            {
                path: 'entidades',
                children: [
                    { index: true, element: <EntityPage /> },
                    ...fuelStationRoutes,
                    // Fica por último: ':role' casa com qualquer segmento,
                    // então as rotas de nome fixo precisam vir antes.
                    ...entityRoutes,
                ],
            },
            {
                path: 'frota',
                children: [
                    { index: true, element: <FleetPage /> },
                    ...vehicleRoutes,
                ],
            },
        ],
    },
]
