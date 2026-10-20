import { createBrowserRouter, Navigate } from 'react-router'

import Home from '@/pages/Home'
import NotFound from '@/pages/NotFound'
import ProfilePage from '@/pages/ProfilePage'

import authRoutes from './auth/authRoutes'
import moduleRoutes from './moduleRoutes'
import { RequireCompany } from './RequireCompany'
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
      ...moduleRoutes,
      ...systemRoutes,
    ],
  },
  { path: '*', element: <NotFound /> },
  // Sem layout. Exemplo: { path: '/login', element: <Login /> },
])
