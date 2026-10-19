import { createBrowserRouter, Navigate } from 'react-router'

import { AppLayout } from '@/layouts/AppLayout'
import Home from '@/pages/Home'
import NotFound from '@/pages/NotFound'
import Perimeter from '@/pages/Perimeter'

import authRoutes from './auth/authRoutes'
import systemRoutes from './system/systemRoutes'

export const router = createBrowserRouter([
  // Se for para a raiz, redireciona para /app (depois colocar validação para se tive rlogado)
  { path: '/', element: <Navigate to="/app" replace /> },


  ...authRoutes,

  {
    path: '/app',
    element: <AppLayout />,
    children: [
      { index: true, element: <Home /> },
      { path: 'rastreamento', element: <Perimeter /> },
      ...systemRoutes,
    ],
  },
  { path: '*', element: <NotFound /> },
  // Sem layout. Exemplo: { path: '/login', element: <Login /> },
])
