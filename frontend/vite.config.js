import path from "node:path";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";
const __dirname = import.meta.dirname;

const BASE = "/acn/";
const DEV_HOST = "127.0.0.1";
const DEV_PORT = 5173;
const DEV_ORIGIN = `http://${DEV_HOST}:${DEV_PORT}`;

// ---------- Alias ----------
const src = (...segments) => path.resolve(__dirname, "src", ...segments);

const ALIAS_DIRS = ["api", "assets", "router", "components", "composables", "modules", "layouts", "stores", "utils", "types", "offline"];

const alias = {
  "@": src(),
  ...Object.fromEntries(ALIAS_DIRS.map((dir) => [`@${dir}`, src(dir)])),
};

// ---------- PWA ----------
// CATATAN: fungsi di dalam `workbox.runtimeCaching` di-serialize ke sw.js,
// jadi harus mandiri (tidak boleh memakai variabel dari luar fungsi).
const pwa = VitePWA({
  registerType: "autoUpdate",
  // offline.html harus ada di /public agar tersalin ke outDir dan masuk precache.
  includeAssets: ["offline.html"],

  workbox: {
    cleanupOutdatedCaches: true,
    maximumFileSizeToCacheInBytes: 7 * 1024 * 1024,
    globPatterns: ["**/*.{js,css,html,ico,png,woff,woff2}"],
    globIgnores: ["**/tabler-icons-*.svg"],
    ignoreURLParametersMatching: [/^utm_/, /^fbclid$/],

    navigateFallback: null,

    runtimeCaching: [
      {
        // Semua navigasi aplikasi di bawah /acn/.
        // Jangan menyimpan HTML halaman pengguna ke runtime cache.
        // urlPattern: ({ request, url }) => request.mode === "navigate" && /^\/acn(\/|$)/.test(url.pathname) && !/^\/(acn\/)?api\//.test(url.pathname) && !/\/\.well-known\//.test(url.pathname),
        urlPattern: ({ request, url }) => request.mode === "navigate" && (url.pathname === "/acn/" || url.pathname === "/acn") && !url.search,
        handler: "NetworkOnly",

        options: {
          plugins: [
            {
              // Jika navigasi gagal karena jaringan/server tidak tersedia,
              // kembalikan offline.html yang sudah masuk precache.
              handlerDidError: async () => {
                const fallback = await caches.match("/acn/offline.html", { ignoreSearch: true });

                if (fallback) return fallback;

                return new Response(
                  `<!doctype html>
              <html lang="id">
                <head>
                  <meta charset="utf-8">
                  <meta name="viewport" content="width=device-width, initial-scale=1">
                  <title>Offline - Cakra</title>
                </head>
                <body style="font-family:Arial,sans-serif;text-align:center;padding:3rem">
                  <h1>Koneksi tidak tersedia</h1>
                  <p>Silakan periksa koneksi dan coba kembali.</p>
                </body>
              </html>`,
                  {
                    status: 200,
                    headers: {
                      "Content-Type": "text/html; charset=utf-8",
                    },
                  },
                );
              },
            },
          ],
        },
      },

      // Pertahankan konfigurasi CacheFirst untuk SVG ikon
      {
        urlPattern: /\/acn\/assets\/tabler-icons-.*\.svg$/,
        handler: "CacheFirst",
        options: {
          cacheName: "acn-icons",
          cacheableResponse: { statuses: [200] },
          expiration: {
            maxEntries: 1,
            maxAgeSeconds: 60 * 60 * 24 * 30,
          },
        },
      },
    ],
  },

  manifest: {
    name: "Cakra Enterprise POS",
    short_name: "CakraPOS",
    lang: "id",
    start_url: BASE,
    scope: BASE,
    display: "standalone",
    theme_color: "#4f46e5",
    background_color: "#f8fafc",
    // Isi minimal ikon 192x192 dan 512x512 agar PWA bisa diinstal.
    icons: [],
  },
});

// ---------- Config ----------
export default defineConfig({
  base: BASE,

  plugins: [vue(), pwa],

  resolve: { alias },

  server: {
    host: DEV_HOST,
    port: DEV_PORT,
    strictPort: true,
    origin: DEV_ORIGIN,
    cors: {
      origin: ["https://cakra.test", DEV_ORIGIN],
      credentials: true,
    },
    hmr: { host: DEV_HOST, protocol: "ws" },
    fs: { strict: false, allow: [".."] },
  },

  optimizeDeps: {
    exclude: ["paperizer"],
  },

  build: {
    outDir: "../public/acn",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: src("main.ts"),
      checks: { pluginTimings: false },
    },
  },
});
