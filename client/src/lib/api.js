import axios from 'axios';

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    // timeout: 5000,
    headers: {
        Accept: 'application/json',
    },
})