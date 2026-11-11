import path from "node:path";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import AutoImport from "unplugin-auto-import/vite";
import { fileURLToPath, URL } from "node:url";

const __dirname = import.meta.dirname;

export default defineConfig({
  base: "/acn/",

  plugins: [
    vue(),

    AutoImport({
      imports: ["vue", "vue-router", "pinia"],
      dirs: ["./src/composables"],
      dts: "./src/auto-imports.d.ts",
      vueTemplate: true,
    }),
  ],

  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
      "@api": path.resolve(__dirname, "./src/api"),
      "@assets": path.resolve(__dirname, "./src/assets"),
      "@router": path.resolve(__dirname, "./src/router"),
      "@components": path.resolve(__dirname, "./src/components"),
      "@composables": path.resolve(__dirname, "./src/composables"),
      "@modules": path.resolve(__dirname, "./src/modules"),
      "@layouts": path.resolve(__dirname, "./src/layouts"),
      "@stores": path.resolve(__dirname, "./src/stores"),
      "@utils": path.resolve(__dirname, "./src/utils"),
      "@types": path.resolve(__dirname, "./src/types"),
    },
  },

  server: {
    host: "127.0.0.1",
    port: 5173,
    strictPort: true,

    origin: "http://127.0.0.1:5173",

    cors: {
      origin: ["http://cakra.test", "http://127.0.0.1:5173"],
      credentials: true,
    },

    hmr: {
      host: "127.0.0.1",
      protocol: "ws",
    },

    fs: {
      strict: false,
      allow: [".."],
    },
  },

  build: {
    outDir: "../public/acn",
    emptyOutDir: true,
    manifest: true,

    rollupOptions: {
      input: path.resolve(__dirname, "src/main.ts"),
    },
  },
});
