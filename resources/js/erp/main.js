import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./App.vue";
import { baseUrl, siteUrl } from "./utils/url.js";

const app = createApp(App);
const pinia = createPinia();

app.mixin({
  methods: {
    baseUrl,
    siteUrl,
  },
});

globalThis.baseUrl = baseUrl;
globalThis.siteUrl = siteUrl;

app.use(pinia);
app.use(router);

app.mount("#erp-app");

export { app, pinia, router };
