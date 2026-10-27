import StationInvitePage from '@/pages/access/StationInvitePage'
import StationMembersPage from '@/pages/access/StationMembersPage'
import StationCompaniesPage from '@/pages/station/StationCompaniesPage'
import StationHome from '@/pages/station/StationHome'

import { RequireManager } from '../RequireManager'

export default [
    { index: true, element: <StationHome /> },
    { path: 'empresas', element: <StationCompaniesPage /> },
    {
        element: <RequireManager home="/posto" />,
        children: [
            { path: 'usuarios', element: <StationMembersPage /> },
            { path: 'usuarios/convidar', element: <StationInvitePage /> },
        ],
    },
]
