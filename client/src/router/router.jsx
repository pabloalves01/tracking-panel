import { createBrowserRouter, Navigate } from 'react-router'

import Home from '@/pages/Home'
import NotFound from '@/pages/NotFound'
import Perimeter from '@/pages/Perimeter'
import ProfilePage from '@/pages/ProfilePage'
import UsersPage from '@/pages/access/UsersPage'

import authRoutes from './auth/authRoutes'
import registryRoutes from './registry/registryRoutes'
import { RequireCompany } from './RequireCompany'
import { RequireFuelStation } from './RequireFuelStation'
import stationRoutes from './station/stationRoutes'
import systemRoutes from './system/systemRoutes'

export const router = createBrowserRouter([
  { path: '/', element: <Navigate to="/app" replace /> },


  ...authRoutes,

  {
    path: '/app',
    element: <RequireCompany />,
    children: [
      { index: true, element: <Home /> },
      { path: 'perfil', element: <ProfilePage /> },
      { path: 'operacoes/rastreamento', element: <Perimeter /> },
      { path: 'usuarios', element: <UsersPage scope="company" /> },
      ...registryRoutes,
      ...systemRoutes,
    ],
  },
  {
    path: '/posto',
    element: <RequireFuelStation />,
    children: stationRoutes,
  },
  { path: '*', element: <NotFound /> },
  // Sem layout. Exemplo: { path: '/login', element: <Login /> },
])
