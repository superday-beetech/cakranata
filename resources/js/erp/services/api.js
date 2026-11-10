const API_BASE = "/api/v1";

async function request(endpoint, options = {}) {
  const { method = "GET", body, auth = "cookie" } = options;

  const response = await window.apiFetch(`${API_BASE}${endpoint}`, {
    method,
    auth,
    ...(body !== undefined ? { body } : {}),
  });

  const result = response.data;

  if (!response.ok || result?.error) {
    const message = Array.isArray(result?.message) ? result.message.join(", ") : result?.message || `Request gagal (${response.status}).`;

    const error = new Error(message);
    error.status = response.status;
    error.response = result;

    throw error;
  }

  return result?.data;
}

export const api = {
  get: (url) => request(url),

  post: (url, body) => request(url, { method: "POST", body }),

  put: (url, body) => request(url, { method: "PUT", body }),

  patch: (url, body) => request(url, { method: "PATCH", body }),

  delete: (url, body) => request(url, { method: "DELETE", body }),
};
