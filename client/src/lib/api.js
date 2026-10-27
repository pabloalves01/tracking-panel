import axios from 'axios';

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    // timeout: 5000,
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.data?.code === 'context_access_lost') {
            if (window.location.pathname !== '/auth/contextos') {
                window.location.assign('/auth/contextos')
            }
            return Promise.reject(error)
        }

        const isLogin = error.config?.url?.includes('/auth/login')
        const onAuthScreen = window.location.pathname.startsWith('/auth/')
        if (error.response?.status === 403) {
            const data = error.response.data
            const message = data && typeof data === 'object' ? data.message : ''
            const english = !message
                || message === 'This action is unauthorized.'
                || message === 'This action is unauthorized'
                || message === 'Forbidden'
            if (english) {
                error.response.data = {
                    ...(data && typeof data === 'object' ? data : {}),
                    message: 'Você não tem permissão para esta ação.',
                }
            }
        }

        if (error.response?.status === 401 && !isLogin && !onAuthScreen) {
            localStorage.removeItem('token')
            if (window.location.pathname !== '/auth/login') {
                window.location.assign('/auth/login')
            }
        }
        return Promise.reject(error)
    },
)