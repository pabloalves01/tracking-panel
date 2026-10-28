import { Navigate } from 'react-router'

import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage'
import InvitationPage from '@/pages/auth/InvitationPage'
import LoginPage from '@/pages/auth/LoginPage'
import RegisterFuelStationPage from '@/pages/auth/RegisterFuelStationPage'
import SelectOrganizationPage from '@/pages/auth/SelectOrganizationPage'

export default [
    {
        path: '/auth',
        children: [
            { path: 'login', element: <LoginPage /> },
            { path: 'register', element: <Navigate to="/auth/login" replace /> },
            { path: 'register/posto', element: <RegisterFuelStationPage /> },
            { path: 'convite/:token', element: <InvitationPage /> },
            { path: 'organizacoes', element: <SelectOrganizationPage /> },
            { path: 'forgot-password', element: <ForgotPasswordPage /> },

            // Endereços antigos: "contexto" virou "organização".
            { path: 'contextos', element: <Navigate to="/auth/organizacoes" replace /> },
            { path: 'empresas', element: <Navigate to="/auth/organizacoes" replace /> },
        ],
    },
]
