const appBaseUrl = import.meta.env.VITE_APP_BASE_URL;
const themeBaseUrl = import.meta.env.VITE_THEME_BASE_URL;

export const appConfig = {
  baseUrl: appBaseUrl.endsWith("/")
    ? appBaseUrl
    : `${appBaseUrl}/`,

  themeUrl: themeBaseUrl.endsWith("/")
    ? themeBaseUrl
    : `${themeBaseUrl}/`,
};
