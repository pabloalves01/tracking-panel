import { Navigate } from 'react-router'

import LoginPage from '@/pages/auth/LoginPage'
import RegisterFuelStationPage from '@/pages/auth/RegisterFuelStationPage'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage'
import InvitationPage from '@/pages/auth/InvitationPage'
import SelectContextPage from '@/pages/auth/SelectContextPage'
export default [
    {
        path: '/auth',
        children: [
            { path: 'login', element: <LoginPage /> },
            { path: 'register', element: <Navigate to="/auth/login" replace /> },
            { path: 'register/posto', element: <RegisterFuelStationPage /> },
            { path: 'convite/:token', element: <InvitationPage /> },
            { path: 'empresas', element: <Navigate to="/auth/contextos" replace /> },
            { path: 'contextos', element: <SelectContextPage /> },
            { path: 'forgot-password', element: <ForgotPasswordPage /> },
        ],
    },
]