import StationInvitePage from '@/pages/access/StationInvitePage'
import StationMembersPage from '@/pages/access/StationMembersPage'
import StationCompaniesPage from '@/pages/station/StationCompaniesPage'
import StationHome from '@/pages/station/StationHome'

export default [
    { index: true, element: <StationHome /> },
    { path: 'empresas', element: <StationCompaniesPage /> },
    { path: 'usuarios', element: <StationMembersPage /> },
    { path: 'usuarios/convidar', element: <StationInvitePage /> },
]
