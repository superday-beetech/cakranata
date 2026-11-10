
import "../../public/themes/adminmart/css/styles.css";
import "../../public/themes/adminmart/plugins/bootstrap-icons/bootstrap-icons.min.css";
import "../../public/themes/adminmart/plugins/fontawesome-free/css/all.min.css";
import "../../public/themes/adminmart/plugins/icheck-bootstrap/icheck-bootstrap.min.css";

import "../../public/themes/adminmart/plugins/bootstrap/js/bootstrap.bundle.min.js";
import "../../public/themes/adminmart/plugins/simplebar/js/simplebar.min.js";
import "../../public/themes/adminmart/js/theme.js";
import "../../public/themes/adminmart/js/sidebarmenu.js";
import "../../public/themes/adminmart/js/app.min.js";

import { createApp } from "vue";

import App from "./App.vue";
import router from "@router";
import pinia from "@/stores";

import { useSettingsStore } from "@stores/settings";
import { useNotificationStore } from "@/composables/notification";
import { installGlobalErrorHandler } from "@/composables/globalErrorHandler";

const app = createApp(App);

app.use(pinia);

// Install global error handler after Pinia activation.
installGlobalErrorHandler();

// Initialize application settings.
const settingsStore = useSettingsStore(pinia);

settingsStore.initialize().catch((error: unknown) => {
    console.error("[Settings] Initialization failed:", error);

    const notification = useNotificationStore(pinia);

    notification.error(
        "Pengaturan aplikasi gagal dimuat. Beberapa fitur mungkin tidak tersedia.",
        {
            title: "Inisialisasi Aplikasi Gagal",
            autoClose: false,
            closable: true,
        },
    );
});

app.use(router);

app.mount("#app");

// Indicate that Vue has successfully mounted.
(
    window as typeof window & {
        __ACN_APP_MOUNTED__?: boolean;
    }
).__ACN_APP_MOUNTED__ = true;
