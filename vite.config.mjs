import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "node:path";
import { writeFileSync, unlinkSync, existsSync } from "node:fs";

const hotFile = resolve(import.meta.dirname, "public/hot");
const devServer = "http://localhost:5173";

export default defineConfig(({ command }) => ({
  configLoader: "runner",

  plugins: [
    vue(),

    {
      name: "ci4-vite-hot-file",

      configureServer() {
        writeFileSync(hotFile, devServer);

        return () => {
          // Tidak menghapus hot file di sini.
          // Hapus saat server ditutup melalui closeBundle
          // tidak dijamin berjalan pada dev server.
        };
      },

      closeBundle() {
        if (command === "build" && existsSync(hotFile)) {
          unlinkSync(hotFile);
        }
      },
    },
  ],

  // Dev memakai root; build tetap memakai /assets/build/.
  base: command === "serve" ? "/" : "/assets/build/",

  publicDir: false,

  server: {
    host: "localhost",
    port: 5173,
    strictPort: true,
    origin: devServer,

    cors: {
      origin: ["http://cakra.test", "http://cakra.co.id", , "http://localhost:8080"],
    },

    hmr: {
      host: "localhost",
    },
  },

  build: {
    outDir: "public/assets/build",
    emptyOutDir: false,
    manifest: "manifest.json",

    rollupOptions: {
      input: {
        app: resolve(import.meta.dirname, "resources/js/app.js"),
        erp: resolve(import.meta.dirname, "resources/js/erp/main.js"),
      },
    },
  },
}));

/*

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "node:path";

export default defineConfig({
  configLoader: "runner",
  plugins: [vue()],

  // Aset hasil build diakses melalui URL ini.
  base: "/assets/build/",

  // Jangan gunakan folder public CI4 sebagai publicDir Vite.
  publicDir: false,

  build: {
    outDir: "public/assets/build",
    emptyOutDir: false,
    manifest: "manifest.json",

    rollupOptions: {
      input: {
        app: resolve(import.meta.dirname, "resources/js/app.js"),
      },
    },
  },
});

*/
