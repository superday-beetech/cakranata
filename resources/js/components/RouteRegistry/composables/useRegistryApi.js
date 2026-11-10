export function useRegistryApi(apiBase) {
  const base = String(apiBase ?? "").replace(/\/+$/, "");

  function csrfHeaders() {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    };

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta?.content) {
      headers["X-CSRF-TOKEN"] = meta.content;
    }

    return headers;
  }

  async function request(path, options = {}) {
    const headers = {
      ...csrfHeaders(),
      ...(options.headers ?? {}),
    };

    const response = await fetch(`${base}${path}`, {
      ...options,
      headers,
      credentials: "same-origin",
    });

    const contentType = response.headers.get("content-type") ?? "";

    let result = null;

    if (contentType.includes("application/json")) {
      result = await response.json();
    } else {
      result = await response.text();
    }

    // Perbarui token CSRF jika backend mengirim token baru.
    if (result && typeof result === "object" && result.csrf_token) {
      const meta = document.querySelector('meta[name="csrf-token"]');

      if (meta) {
        meta.content = result.csrf_token;
      }
    }

    if (!response.ok || (result && typeof result === "object" && (result.status === false || result.error))) {
      const message = result?.message ?? result?.error ?? `HTTP ${response.status}`;

      throw new Error(message);
    }

    return result;
  }

  function get(path) {
    return request(path, {
      method: "GET",
    });
  }

  function post(path, data = {}) {
    return request(path, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });
  }

  function patch(path, data = {}) {
    return request(path, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });
  }

  return {
    request,
    get,
    post,
    patch,
  };
}
