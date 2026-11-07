import path from "node:path";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import AutoImport from "unplugin-auto-import/vite";
import { fileURLToPath, URL } from "node:url";

const __dirname = import.meta.dirname;

// import { env } from "@/config/env";

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
      "@": fileURLToPath(new URL("./src", import.meta.url)),
      "@api": fileURLToPath(new URL("./src/api", import.meta.url)),
      "@assets": fileURLToPath(new URL("./src/assets", import.meta.url)),
      "@router": fileURLToPath(new URL("./src/router", import.meta.url)),
      "@components": fileURLToPath(new URL("./src/components", import.meta.url)),
      "@composables": fileURLToPath(new URL("./src/composables", import.meta.url)),
      "@modules": fileURLToPath(new URL("./src/modules", import.meta.url)),
      "@layouts": fileURLToPath(new URL("./src/layouts", import.meta.url)),
      "@stores": fileURLToPath(new URL("./src/stores", import.meta.url)),
      "@utils": fileURLToPath(new URL("./src/utils", import.meta.url)),
      "@types": fileURLToPath(new URL("./src/types", import.meta.url)),
    },
  },

  server: {
    host: "localhost",
    port: 5173,
    strictPort: true,

    cors: {
      origin: "http://cakra.test",
    },

    hmr: {
      host: "localhost",
    },

    watch: {
      usePolling: true,
      interval: 500,
    },
    // open: true,

    /*
        proxy: {
            "/api": {
                target: "http://localhost:8080",
                changeOrigin: true,
            },
            "/themes": {
                target: "http://localhost:8080",
                changeOrigin: true,
            },
        },
        */
  },

  build: {
    outDir: "../public/acn",
    emptyOutDir: true,
    manifest: true,

    rollupOptions: {
      // input: path.dirname(__dirname, "src/main.ts"),
      input: path.resolve(__dirname, "src/main.ts"),
    },
  },
});
