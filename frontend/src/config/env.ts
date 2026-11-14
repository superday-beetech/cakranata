/*src/config/env.ts*/
import { getOrigin, getBaseUrl, getApiBaseUrl, isHttps } from "@/utils/url";
export const env = {
  baseURL: getOrigin(),
  apiBaseURL: getApiBaseUrl(),
  appBaseURL: import.meta.env.VITE_APP_BASE_URL,
  isProduction: import.meta.env.PROD,
} as const;
