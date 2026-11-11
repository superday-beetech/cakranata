export const env = {
    baseURL: import.meta.env.VITE_BASE_URL,
    apiBaseURL: import.meta.env.VITE_API_BASE_URL,
    appBaseURL: import.meta.env.VITE_APP_BASE_URL,
    isProduction: import.meta.env.PROD,
} as const;
