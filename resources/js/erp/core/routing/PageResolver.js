const pageModules = import.meta.glob(["../../pages/*.vue", "../../pages/**/*.vue"]);

function normalizePath(value) {
  return String(value || "")
    .replace(/\\/g, "/")
    .replace(/^\/+|\/+$/g, "")
    .toLowerCase();
}

function findPage(path) {
  const expected = normalizePath(path);

  const entry = Object.entries(pageModules).find(([file]) => {
    return normalizePath(file.replace("../../pages/", "")) === expected;
  });

  return entry?.[1] ?? null;
}

export function resolvePage(url) {
  const path = normalizePath(url);

  if (!path) return null;

  // Halaman yang sudah tersedia.
  const aliases = {
    "admin/dashboard": "Dashboard.vue",
    "backend/dashboard": "Dashboard.vue",
  };

  if (aliases[path]) {
    return findPage(aliases[path]);
  }

  // Cari halaman berdasarkan struktur folder.
  const expected =
    path
      .split("/")
      .map((part) =>
        part
          .split(/[-_\s]+/)
          .filter(Boolean)
          .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
          .join(""),
      )
      .join("/") + ".vue";

  return findPage(expected) ?? findPage("Placeholder.vue");
}
