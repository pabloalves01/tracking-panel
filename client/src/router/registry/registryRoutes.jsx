import EntityPage from '@/pages/registry/EntityPage'
import FleetPage from '@/pages/registry/FleetPage'
import RegistryPage from '@/pages/registry/RegistryPage'

import carrierRoutes from './carrierRoutes'
import customerRoutes from './customerRoutes'
import driverRoutes from './driverRoutes'
import employeeRoutes from './employeeRoutes'
import supplierRoutes from './supplierRoutes'
import vehicleOwnerRoutes from './vehicleOwnerRoutes'
import vehicleResponsibleRoutes from './vehicleResponsibleRoutes'
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
                    ...supplierRoutes,
                    ...customerRoutes,
                    ...employeeRoutes,
                    ...carrierRoutes,
                    ...driverRoutes,
                    ...vehicleOwnerRoutes,
                    ...vehicleResponsibleRoutes,
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
