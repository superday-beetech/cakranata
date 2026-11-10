// /js/api.js

window.apiFetch = async (url, options = {}) => {
  // 1. Ambil token CSRF terbaru dari meta tag halaman
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute("content") : "";

  // 2. Set default options untuk Fetch
  options.headers = options.headers || {};

  // Jika mengirim data JSON atau method selain GET, pastikan header CSRF terpasang
  if (options.method && options.method.toUpperCase() !== "GET") {
    options.headers["X-CSRF-TOKEN"] = csrfToken;
    options.headers["X-Requested-With"] = "XMLHttpRequest";

    // Jika body berupa object/array, otomatis ubah ke JSON
    if (options.body && typeof options.body === "object" && !(options.body instanceof FormData)) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(options.body);
    }
  }

  try {
    // Eksekusi fetch asli
    const response = await fetch(url, options);

    // Ambil teks respons untuk dicek apakah valid JSON
    const textResponse = await response.text();
    let data;

    try {
      data = JSON.parse(textResponse);
    } catch (error) {
      console.error("Respons server bukan JSON:", {
        url,
        httpStatus: response.status,
        contentType: response.headers.get("content-type"),
        body: textResponse.slice(0, 1500),
      });

      throw new Error(`HTTP ${response.status}: server mengembalikan respons non-JSON.`);
    }

    /*     try {
      data = JSON.parse(textResponse);
    } catch (e) {
      throw new Error("Respon server bukan format JSON yang valid.");
    }
 */
    // 3. INTERCEPTOR UTAMA: Otomatis perbarui token CSRF jika ada di respons server
    if (data && data.csrf_token) {
      // Perbarui meta tag global
      if (csrfTokenMeta) {
        csrfTokenMeta.setAttribute("content", data.csrf_token);
      }

      // Perbarui input hidden di form statis (jika ada)
      const csrfInput = document.getElementById("csrf-input");
      if (csrfInput) {
        csrfInput.value = data.csrf_token;
      }
    }

    // Kembalikan objek response kustom agar mudah di-chain di controller frontend
    return {
      ok: response.ok,
      status: response.status,
      data: data,
    };
  } catch (error) {
    console.error("Global API Interceptor Error:", error);
    throw error;
  }
};
