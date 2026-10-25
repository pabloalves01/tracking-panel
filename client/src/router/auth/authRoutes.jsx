import { Navigate } from 'react-router'

import LoginPage from '@/pages/auth/LoginPage'
import RegisterPage from '@/pages/auth/RegisterPage'
import RegisterFuelStationPage from '@/pages/auth/RegisterFuelStationPage'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage'
import SelectContextPage from '@/pages/auth/SelectContextPage'
import InvitationPage from '@/pages/auth/InvitationPage'

export default [
    {
        path: '/auth',
        children: [
            { path: 'login', element: <LoginPage /> },
            { path: 'register', element: <RegisterPage /> },
            { path: 'register/posto', element: <RegisterFuelStationPage /> },
            { path: 'empresas', element: <Navigate to="/auth/contextos" replace /> },
            { path: 'contextos', element: <SelectContextPage /> },
            { path: 'convite/:token', element: <InvitationPage /> },
            { path: 'forgot-password', element: <ForgotPasswordPage /> },
        ],
    },
]