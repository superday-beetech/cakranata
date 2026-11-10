const API_BASE = "/api/v1/navigation";

export async function fetchMenus() {
  const response = await fetch(API_BASE, {
    method: "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    throw new Error(`Gagal mengambil menu: HTTP ${response.status}`);
  }

  const result = await response.json();

  // Menyesuaikan beberapa format respons API yang umum.
  const menus = result?.data?.menus ?? result?.data ?? result?.menus;

  if (!Array.isArray(menus)) {
    throw new Error("Format respons API menu bukan array.");
  }

  return menus;
}
