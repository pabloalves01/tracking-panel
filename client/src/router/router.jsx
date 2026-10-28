import { createBrowserRouter, Navigate } from 'react-router'

import Home from '@/pages/Home'
import NotFound from '@/pages/NotFound'
import Perimeter from '@/pages/Perimeter'
import ProfilePage from '@/pages/ProfilePage'

import accessRoutes from './access/accessRoutes'
import authRoutes from './auth/authRoutes'
import registryRoutes from './registry/registryRoutes'
import { RequireOrganization } from './RequireOrganization'
import stationRoutes from './station/stationRoutes'
import systemRoutes from './system/systemRoutes'

export const router = createBrowserRouter([
    { path: '/', element: <Navigate to="/app" replace /> },

    ...authRoutes,

    {
        path: '/app',
        element: <RequireOrganization type="company" />,
        children: [
            { index: true, element: <Home /> },
            { path: 'perfil', element: <ProfilePage /> },
            { path: 'operacoes/rastreamento', element: <Perimeter /> },
            ...accessRoutes,
            ...registryRoutes,
            ...systemRoutes,
        ],
    },
    {
        path: '/posto',
        element: <RequireOrganization type="fuel_station" />,
        children: stationRoutes,
    },
    { path: '*', element: <NotFound /> },
])
