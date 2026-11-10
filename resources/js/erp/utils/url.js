const config = window.APP_CONFIG ?? {};

// Deteksi protokol, domain, dan port dari browser.
const origin = window.location.origin;

// Hilangkan slash berlebih di akhir base path.
function normalizeBase(value) {
  const normalized = String(value || "/").replace(/\/+$/, "");
  return normalized ? `${normalized}/` : "/";
}

// Prioritaskan URL CI4, fallback ke origin browser.
export const baseURL = config.baseURL ? new URL(config.baseURL, origin).href.replace(/\/?$/, "/") : normalizeBase(origin);

// siteURL CI4 bisa berisi index.php atau konfigurasi routing.
export const siteURL = config.siteURL ? config.siteURL : baseURL;

// Membuat URL aset, misalnya /uploads/ChatBc.png.
export function baseUrl(path = "") {
  const base = baseURL.endsWith("/") ? baseURL : `${baseURL}/`;
  return new URL(String(path).replace(/^\/+/, ""), base).href;
}

// Membuat URL rute CI4, misalnya dashboard atau api/v1/auth/login.
export function siteUrl(path = "") {
  const base = siteURL.endsWith("/") ? siteURL : `${siteURL}/`;
  return new URL(String(path).replace(/^\/+/, ""), base).href;
}
