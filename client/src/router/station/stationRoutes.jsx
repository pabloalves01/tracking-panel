import StationCompaniesPage from '@/pages/station/StationCompaniesPage'
import StationHome from '@/pages/station/StationHome'

export default [
    { index: true, element: <StationHome /> },
    { path: 'empresas', element: <StationCompaniesPage /> },
]
