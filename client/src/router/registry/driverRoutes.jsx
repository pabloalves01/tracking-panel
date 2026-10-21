import DriverFormPage from '@/pages/registry/DriverFormPage'
import DriversPage from '@/pages/registry/DriversPage'

export default [
    { path: 'motoristas', element: <DriversPage /> },
    { path: 'motoristas/novo', element: <DriverFormPage /> },
    { path: 'motoristas/:uuid/editar', element: <DriverFormPage /> },
]
