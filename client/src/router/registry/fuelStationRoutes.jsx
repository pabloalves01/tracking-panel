import FuelStationFormPage from '@/pages/registry/FuelStationFormPage'
import FuelStationsPage from '@/pages/registry/FuelStationsPage'

export default [
    { path: 'postos', element: <FuelStationsPage /> },
    { path: 'postos/novo', element: <FuelStationFormPage /> },
]
