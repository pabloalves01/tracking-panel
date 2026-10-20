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
        const isLogin = error.config?.url?.includes('/auth/login')
        if (error.response?.status === 401 && !isLogin) {
            localStorage.removeItem('token')
            if (window.location.pathname !== '/auth/login') {
                window.location.assign('/auth/login')
            }
        }
        return Promise.reject(error)
    },
)