import EntityPage from '@/pages/registry/EntityPage'
import RegistryPage from '@/pages/registry/RegistryPage'
import fleetRoutes from '@/router/fleet/fleetRoutes'

import entityRoutes from './entityRoutes'
import fuelStationRoutes from './fuelStationRoutes'

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
            ...fleetRoutes,
        ],
    },
]
