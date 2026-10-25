import StationCompaniesPage from '@/pages/station/StationCompaniesPage'
import StationHome from '@/pages/station/StationHome'
import UsersPage from '@/pages/access/UsersPage'

export default [
    { index: true, element: <StationHome /> },
    { path: 'empresas', element: <StationCompaniesPage /> },
    { path: 'usuarios', element: <UsersPage scope="station" /> },
]
