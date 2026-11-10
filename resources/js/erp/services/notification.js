import { showNotify } from "../utils/showNotify.js";

export const notify = {
  success(message, title = "Berhasil") {
    return showAlert(message, "success", 5000, title);
  },

  error(message, title = "Terjadi Kesalahan") {
    return showAlert(message, "danger", 7000, title);
  },

  warning(message, title = "Peringatan") {
    return showAlert(message, "warning", 6000, title);
  },

  info(message, title = "Informasi") {
    return showAlert(message, "info", 5000, title);
  },

  show(message, type = "info", timeout = 5000, title = null, icon = null, position = "top-right") {
    return showAlert(message, type, timeout, title, icon, position);
  },
};
