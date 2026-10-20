import { createBrowserRouter, Navigate } from 'react-router'

import Home from '@/pages/Home'
import NotFound from '@/pages/NotFound'
import Perimeter from '@/pages/Perimeter'

import authRoutes from './auth/authRoutes'
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
      { path: 'rastreamento', element: <Perimeter /> },
      ...systemRoutes,
    ],
  },
  { path: '*', element: <NotFound /> },
  // Sem layout. Exemplo: { path: '/login', element: <Login /> },
])
