import axios from "axios";
import { getApiBaseUrl } from "@/utils/url";
export const api = axios.create({
  baseURL: getApiBaseUrl() || "https://api.escuelajs.co/api/v1",
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Interceptor Request: Otomatis selipkan Bearer Token dari localStorage
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("auth_token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);
