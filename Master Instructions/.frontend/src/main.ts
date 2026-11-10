import "../../public/themes/adminmart/css/styles.css";
import "../../public/themes/adminmart/plugins/bootstrap-icons/bootstrap-icons.min.css";
import "../../public/themes/adminmart/plugins/fontawesome-free/css/all.min.css";
import "../../public/themes/adminmart/plugins/icheck-bootstrap/icheck-bootstrap.min.css";
import "../../public/themes/adminmart/plugins/bootstrap/js/bootstrap.bundle.min.js";
import "../../public/themes/adminmart/plugins/simplebar/js/simplebar.min.js";
import "../../public/themes/adminmart/js/theme.js";
import "../../public/themes/adminmart/js/sidebarmenu.js";
import "../../public/themes/adminmart/js/app.min.js";
// import "../../public/themes/adminmart/js/superday.js";
// import "../../public/themes/adminmart/css/theme.css";

import { createApp } from "vue";
import App from "./App.vue";
import router from "@router";
import pinia from "@/stores";
import { env } from "@/config/env";
const app = createApp(App);

app.use(pinia);
app.use(router);

// console.log("VITE_BASE_URL", env.baseURL);
// console.log("VITE_API_BASE_URL", env.apiBaseURL);
// console.log("VITE_APP_BASE_URL", env.appBaseURL);
app.mount("#app");
