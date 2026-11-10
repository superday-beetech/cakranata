async function apiFetch(url, options = {}) {
  const metaToken = document.querySelector('meta[name="csrf-token"]');

  const metaHeader = document.querySelector('meta[name="csrf-header"]');

  const csrfToken = metaToken?.content?.trim() || "";
  const csrfHeader = metaHeader?.content?.trim() || "X-CSRF-TOKEN";

  const headers = new Headers(options.headers || {});

  headers.set("X-Requested-With", "XMLHttpRequest");

  if (csrfToken) {
    headers.set(csrfHeader, csrfToken);
  }

  // Jangan menetapkan Content-Type untuk FormData.
  const response = await fetch(url, {
    ...options,
    headers,
    credentials: "same-origin",
  });

  // Ambil token baru jika backend memang mengirimkannya.
  const newToken = response.headers.get(csrfHeader) || response.headers.get("X-CSRF-TOKEN");

  if (newToken && metaToken) {
    metaToken.content = newToken;
  }

  const responseText = await response.text();

  if (options.responseType === "text") {
    return {
      ok: response.ok,
      status: response.status,
      data: responseText,
    };
  }

  let data;

  try {
    data = responseText ? JSON.parse(responseText) : {};
  } catch {
    data = { message: responseText };
  }

  // Gunakan field CSRF khusus, bukan token autentikasi.
  const rotatedToken = data.token;

  if (rotatedToken && metaToken) {
    metaToken.content = rotatedToken;
  }

  return {
    ok: response.ok,
    status: response.status,
    data,
  };
}
async function openModal(modalId, data = {}, title = null) {
  const modal = document.getElementById(modalId);
  const contentArea = document.getElementById(modalId + "Content");

  if (!modal || !contentArea) return;

  contentArea.innerHTML = `
        <div class="loader-box">
            <div class="loader-37"></div>
        </div>
        <div class="text-muted text-center py-4">
            Memuat konten...
        </div>
    `;

  const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
  bsModal.show();

  // Jika tidak ada URL, gunakan konten yang diberikan.
  if (!data.url && data.content) {
    contentArea.innerHTML = data.content;

    if (title) {
      const titleEl = modal.querySelector(".modal-title");
      if (titleEl) titleEl.textContent = title;
    }

    const firstInput = contentArea.querySelector('input:not([type="hidden"]), select, textarea');

    if (firstInput) firstInput.focus();

    if (typeof data.onReady === "function") {
      await data.onReady(modal);
    }

    return;
  }

  try {
    // Request hanya dilakukan melalui apiFetch.
    const result = await apiFetch(data.url, {
      method: "GET",
      responseType: "text",
    });

    if (!result.ok) {
      throw new Error(`Gagal memuat form: HTTP ${result.status}`);
    }

    // Hanya ada satu sumber HTML.
    contentArea.innerHTML = result.data;

    if (data.action) {
      const form = contentArea.querySelector("form");

      if (form) {
        form.setAttribute("action", data.action);
        console.log("Form action ditetapkan:", form.getAttribute("action"));
      } else {
        console.error("Tidak ditemukan elemen form di konten modal:", contentArea.innerHTML);
      }
    }

    if (title) {
      const titleEl = modal.querySelector(".modal-title");

      if (titleEl) {
        titleEl.textContent = title;
      }
    }

    const firstInput = contentArea.querySelector('input:not([type="hidden"]), select, textarea');

    if (firstInput) firstInput.focus();

    if (typeof data.onReady === "function") {
      await data.onReady(modal);
    }
  } catch (err) {
    console.error("Gagal memuat konten modal:", err);

    contentArea.innerHTML = `
            <div class="text-danger text-center py-4">
                Gagal memuat konten dari server.
            </div>
        `;
  }
}

document.addEventListener("click", function (e) {
  if (e.target.closest(".add-button")) {
    e.preventDefault();

    const btn = e.target.closest(".add-button");
    const groupId = btn.dataset.groupId;
    const parentId = btn.dataset.id;
    const btnId = btn.id;

    const url = baseUrl + "addForm?menu_group_id=" + groupId + "&id_parent=" + parentId;
    const title = "Tambah Menu";

    openModal(
      "menuFormModal",
      {
        url: url,
        action: baseUrl + "insert",
        onReady: function () {
          const form = document.getElementById("formMenu");
          if (!form) return;

          const checkedInput = form.querySelector('input[name="link_type"]:checked');
          const checked = checkedInput ? checkedInput.value : null;

          setTimeout(function () {
            if (checked) toggleTipe(checked);

            // pasang event listener change
            form.querySelectorAll('input[name="link_type"]').forEach((input) => {
              input.removeEventListener("change", handleChange);
              input.addEventListener("change", handleChange);
            });

            function handleChange(e) {
              toggleTipe(e.target.value);
            }
          }, 10);
        },
      },
      title
    );

    document.getElementById("menuFormModal").dataset._lastTrigger = btnId;
  }
});

setTimeout(() => {
  const el = document.getElementById("id_parent");
  if (el && el.offsetParent !== null) el.focus();
}, 100);

document.addEventListener("click", function (e) {
  if (e.target.closest(".edit-button")) {
    e.preventDefault();

    const btn = e.target.closest(".edit-button");
    const id = btn.dataset.id;

    openModal(
      "menuFormModal",
      {
        url: baseUrl + "editForm/" + id,
        action: baseUrl + "update/" + id,
        onReady: async function (modal) {
          // <--- Added 'async' here
          const form = document.getElementById("formMenu");
          if (!form) return;

          // --- Inisialisasi logika toggle
          const checkedInput = form.querySelector('input[name="link_type"]:checked');
          if (checkedInput) toggleTipe(checkedInput.value);

          form.querySelectorAll('input[name="link_type"]').forEach((input) => {
            input.addEventListener("change", (ev) => toggleTipe(ev.target.value));
          });

          form.querySelectorAll('input[name="url_content_option"]').forEach((input) => {
            input.addEventListener("change", toggleUrlContentFields);
          });
          toggleUrlContentFields();

          // --- Inisialisasi logika Divider
          const dividerCheckbox = document.getElementById("is_divider");
          const linkInput = document.getElementById("link");
          const initialLinkValue = linkInput.value;
          if (initialLinkValue === "divider") {
            dividerCheckbox.checked = true;
          }

          if (dividerCheckbox && linkInput) {
            function toggleDividerValue() {
              if (dividerCheckbox.checked) {
                linkInput.value = "divider";
                linkInput.setAttribute("readonly", "readonly");
                linkInput.removeAttribute("required");
                linkInput.disabled = false; // Pastikan input tidak dinonaktifkan
              } else {
                linkInput.value = initialLinkValue;
                linkInput.removeAttribute("readonly");
                linkInput.disabled = false;
                linkInput.setAttribute("required", "required");
              }
            }

            toggleDividerValue();
            dividerCheckbox.addEventListener("change", toggleDividerValue);
          } else {
            console.error("Salah satu elemen tidak ditemukan: 'is_divider' atau 'link'.");
          }

          // --- Inisialisasi Icon Picker
          // --- Inisialisasi Icon Picker
          try {
            const baseUrl = document.querySelector('meta[name="baseURL"]').getAttribute("content");

            const response = await fetch(baseUrl + "themes/adminmart/plugins/iconpicker/dist/iconsets/bootstrap5.json");
            const result = await response.json();

            initIconPicker("menu_icon", "select_icon_btn", result);
          } catch (error) {
            console.error("Gagal memuat ikon:", error);
          }
        },
      },
      "Edit"
    );
  }
});

// --- SUBMIT FORM pakai AJAX (trigger tombol simpan)
document.addEventListener("button", function (e) {
  if (e.target.closest("#formMenu")) {
    e.preventDefault();
    document.querySelector(".simpan")?.click();
  }
});

// --- SIMPAN (submit form AJAX)
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".simpan");
  if (!btn) return;
  e.preventDefault();

  const formSelector = "#formMenu";
  const form = document.querySelector(formSelector);
  if (!form) return;

  setButtonLoading(btn, true);

  submitFormAjax(formSelector, {
    url: form.getAttribute("action"),
    modalSelector: "#menuFormModal",

    callback: function (res) {
      if (res.menu_group_id) {
        refreshGroupMenu(res.menu_group_id);
      }
    },

    onSuccess: function () {
      setButtonLoading(btn, false);
    },

    onError: function () {
      setButtonLoading(btn, false);
    },
  });
});

// --- DELETE button
document.addEventListener("click", function (e) {
  if (e.target.closest(".del-button")) {
    const btn = e.target.closest(".del-button");
    const id = btn.dataset.id;
    const dd = btn.closest(".dd");
    const groupId = dd ? dd.dataset.groupId : null;

    Swal.fire({
      title: "Hapus item ini?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus",
    }).then((result) => {
      if (result.isConfirmed) {
        handleMenuAction({
          url: baseUrl + "delete/" + id,
          method: "POST",
          groupId: groupId,
        });
      }
    });
  }
});
async function submitFormAjax(formSelector, options = {}) {
  const form = document.querySelector(formSelector);

  if (!form) {
    console.error("Form tidak ditemukan:", formSelector);
    return;
  }

  const actionUrl = options.url || form.getAttribute("action");

  if (!actionUrl) {
    console.error("URL tujuan form tidak ditemukan.");
    return;
  }

  const btn = form.querySelector(".simpan");
  if (btn) setButtonLoading(btn, true);

  try {
    const formData = new FormData(form);
    const result = await apiFetch(actionUrl, {
      method: (form.method || "POST").toUpperCase(),
      body: formData,
    });

    const { ok, status, data } = result;

    console.log("Submit result:", {
      url: actionUrl,
      status,
      data,
    });

    if (!ok) {
      throw new Error(data?.message || `Request gagal (HTTP ${status})`);
    }

    if (typeof data !== "object" || data === null || !(data.success === true || data.status === "success")) {
      console.error("Respons server bukan JSON sukses:", data);
      throw new Error("Server tidak mengembalikan respons JSON sukses. Periksa Network > Response.");
    }

    if (typeof options.callback === "function") {
      options.callback(data);
    }

    if (typeof options.onSuccess === "function") {
      options.onSuccess(data);
    }

    if (options.modalSelector) {
      const modal = document.querySelector(options.modalSelector);
      if (modal) {
        bootstrap.Modal.getOrCreateInstance(modal).hide();
      }
    }

    return data;
  } catch (err) {
    console.error("Submit form error:", err);

    if (typeof options.onError === "function") {
      options.onError({ message: err.message });
    }

    Swal.fire("Gagal", err.message || "Terjadi kesalahan.", "error");
  } finally {
    if (btn) setButtonLoading(btn, false);
  }
}
// --- Toggle aktif
document.addEventListener("click", async function (e) {
  const btn = e.target.closest(".toggle-aktif");
  if (!btn) return;

  const id = btn.dataset.id;
  const current = btn.dataset.current;

  try {
    const { ok, data } = await apiFetch(baseUrl + "toggle_active_ajax", {
      method: "POST",
      body: new URLSearchParams({ id, current }),
    });

    if (ok && data.status === "success") {
      const icon = btn.querySelector("i");
      if (icon) icon.className = data.icon_class;
      btn.title = data.tooltip;
      btn.dataset.current = data.active;

      Swal.fire("Sukses", data.message, "success");
    } else {
      Swal.fire("Gagal", data.message || "Terjadi kesalahan", "error");
    }
  } catch (err) {
    Swal.fire("Error", "Gagal terhubung ke server", "error");
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("menuFormModal");
  if (!modal) return;

  modal.addEventListener("show.bs.modal", function () {
    modal.removeAttribute("inert");
    modal.removeAttribute("aria-hidden");
    modal.dataset._lastTrigger = document.activeElement?.id || "";
  });

  modal.addEventListener("shown.bs.modal", function () {
    const inputs = modal.querySelectorAll("input, select, textarea, button");
    for (let input of inputs) {
      if (input.type !== "hidden" && input.offsetParent !== null) {
        input.focus();
        break;
      }
    }
  });

  modal.addEventListener("hide.bs.modal", function () {
    if (document.activeElement && modal.contains(document.activeElement)) {
      document.activeElement.blur();
    }
  });

  modal.addEventListener("hidden.bs.modal", function () {
    setTimeout(() => {
      modal.setAttribute("inert", "");
      modal.setAttribute("aria-hidden", "true");
    }, 50);

    const triggerId = modal.dataset._lastTrigger;
    if (triggerId) {
      const trigger = document.getElementById(triggerId);
      if (trigger) trigger.focus();
    }
  });
});

// --- Filter menu
document.addEventListener("input", function (e) {
  if (e.target.matches(".menu-filter")) {
    const keyword = e.target.value;
    const groupId = e.target.dataset.groupId;
    filterMenuTree(keyword, groupId);
  }
});

// --- Reset filter
document.addEventListener("click", function (e) {
  if (e.target.closest(".reset-filter")) {
    const btn = e.target.closest(".reset-filter");
    const groupId = btn.dataset.groupId;
    const input = document.querySelector(`.menu-filter[data-group-id="${groupId}"]`);
    if (input) {
      input.value = "";
      filterMenuTree("", groupId);
    }
  }
});

// --- Label show (simpan teks original sekali saja)
document.querySelectorAll(".label_show").forEach((el) => {
  if (!el.dataset.original) {
    el.dataset.original = el.textContent;
  }
});

async function refreshGroupMenu(groupId) {
  const target = document.getElementById("menu-" + groupId);
  if (!target) return;

  target.innerHTML = '<li class="text-muted p-2">Memuat ulang...</li>';

  try {
    const response = await fetch(baseUrl + "tree/" + groupId);
    if (!response.ok) throw new Error("Gagal");

    const html = await response.text();
    target.innerHTML = html;
  } catch (err) {
    target.innerHTML = '<li class="text-danger p-2">Gagal memuat ulang menu</li>';
  }
}

function filterMenu(keyword) {
  const search = keyword.toLowerCase().trim();
  document.querySelectorAll(".dd-item").forEach((item) => {
    const label = (item.dataset.label || "").toLowerCase();
    const slug = (item.dataset.labelSlug || "").toLowerCase();
    item.style.display = label.includes(search) || slug.includes(search) ? "" : "none";
  });
}

function filterMenuByLabel(slug) {
  document.querySelectorAll(".dd-item").forEach((item) => {
    const itemSlug = item.dataset.labelSlug || "";
    item.style.display = itemSlug.includes(slug) ? "" : "none";
  });
}

function filterMenuTree(keyword, groupId) {
  const search = keyword.toLowerCase().trim();
  const container = document.getElementById(`nestable-group-${groupId}`);
  if (!container) return;

  const items = container.querySelectorAll("li.list-group-item");

  items.forEach((item) => {
    item.style.display = "";
    const labelEl = item.querySelector(".item-label");
    if (!labelEl.dataset.original) {
      labelEl.dataset.original = labelEl.innerText;
    }
    labelEl.innerHTML = labelEl.dataset.original;
  });

  if (!search) return;

  items.forEach((item) => {
    const label = (item.dataset.label || "").toLowerCase();
    if (label.includes(search)) {
      item.style.display = "";
      let parent = item.parentElement.closest("li.list-group-item");
      while (parent) {
        parent.style.display = "";
        parent = parent.parentElement.closest("li.list-group-item");
      }

      const labelEl = item.querySelector(".item-label");
      const raw = labelEl.dataset.original || labelEl.innerText;
      const regex = new RegExp("(" + search + ")", "gi");
      labelEl.innerHTML = raw.replace(regex, "<mark>$1</mark>");
    } else {
      item.style.display = "none";
    }
  });
}

function toggleTipe(val) {
  ["url_wrap", "page_wrap", "category_wrap"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.style.display = "none";
  });

  if (val === "url") {
    const el = document.getElementById("url_wrap");
    if (el) el.style.display = "";
    toggleUrlContentFields();
  } else if (val === "page") {
    const el = document.getElementById("page_wrap");
    if (el) el.style.display = "";
    document.getElementById("url-field").classList.add("d-none");
    document.getElementById("content-field").classList.add("d-none");
  } else if (val === "category") {
    const el = document.getElementById("category_wrap");
    if (el) el.style.display = "";
    document.getElementById("url-field").classList.add("d-none");
    document.getElementById("content-field").classList.add("d-none");
  }
}
function toggleUrlContentFields() {
  const selected = document.querySelector('input[name="url_content_option"]:checked')?.value;
  const urlField = document.getElementById("url-field");
  const contentField = document.getElementById("content-field");

  if (urlField) urlField.classList.add("d-none");
  if (contentField) contentField.classList.add("d-none");

  if (selected === "url") {
    if (urlField) urlField.classList.remove("d-none");
  } else if (selected === "content") {
    if (contentField) contentField.classList.remove("d-none");
  } else if (selected === "both") {
    if (urlField) urlField.classList.remove("d-none");
    if (contentField) contentField.classList.remove("d-none");
  }
}

function setButtonLoading(btn, isLoading = true) {
  if (!btn) return;

  if (isLoading) {
    // simpan text asli agar bisa dikembalikan
    btn.dataset.originalHtml = btn.innerHTML;

    // set loading spinner
    btn.innerHTML = `
            <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
            Loading...
        `;
    btn.disabled = true;
  } else {
    // kembalikan ke html asli
    btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
    btn.disabled = false;
  }
}

function initQuill(container) {
  const editor = container.querySelector(".quill-editor");
  if (!editor) return null;

  // ambil CSRF dan baseUrl
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content;

  const quill = new Quill(editor, {
    theme: "snow",
    placeholder: "Tulis konten di sini...",
    modules: {
      toolbar: [[{ header: [1, 2, 3, false] }], ["bold", "italic", "underline", "strike"], [{ list: "ordered" }, { list: "bullet" }], ["link", "image", "video"], ["clean"]],
    },
  });

  // hidden input
  let hiddenInput = editor.parentElement.querySelector('input[name="content_html"]');
  if (!hiddenInput) {
    hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.name = "content_html";
    editor.parentElement.appendChild(hiddenInput);
  }

  quill.on("text-change", () => (hiddenInput.value = quill.root.innerHTML));

  // upload image
  function uploadImage(file) {
    const data = new FormData();
    data.append("image", file);
    if (csrfHeader && csrfToken) data.append(csrfHeader, csrfToken);

    fetch(baseUrl + "upload_image", { method: "POST", body: data })
      .then((res) => res.text())
      .then((url) => {
        const range = quill.getSelection();
        quill.insertEmbed(range.index, "image", url);
      })
      .catch((err) => console.error(err));
  }

  // toolbar image button
  quill.getModule("toolbar").addHandler("image", () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.click();
    input.onchange = () => {
      const file = input.files[0];
      if (file) uploadImage(file);
    };
  });

  // delete image handler (opsional)
  quill.root.addEventListener("dblclick", function (e) {
    if (e.target.tagName === "IMG") {
      const src = e.target.getAttribute("src");
      if (!src) return;
      const data = new URLSearchParams();
      data.append("src", src);
      if (csrfHeader && csrfToken) data.append(csrfHeader, csrfToken);

      fetch(baseUrl + "delete_image", { method: "POST", body: data })
        .then((res) => res.text())
        .then((txt) => {
          console.log("deleted:", txt);
          e.target.remove();
        })
        .catch((err) => console.error(err));
    }
  });

  return quill;
}
async function initIconPicker() {
  try {
    const baseUrl = document.querySelector('meta[name="baseURL"]').getAttribute("content");
    const response = await fetch(baseUrl + "themes/adminmart/plugins/iconpicker/dist/iconsets/bootstrap5.json");
    const result = await response.json();

    const iconInput = document.querySelector("#menu_icon");

    // defaultValue: ambil dari input, kalau kosong pakai bi-gear
    const defaultIcon = iconInput.value && iconInput.value.trim() !== "" ? iconInput.value : "bi-gear";

    const iconpicker = new Iconpicker(iconInput, {
      icons: result,
      showSelectedIn: document.querySelector(".selected-icon"),
      searchable: true, // aktifkan search
      searchPlaceholder: "Cari ikon...", // placeholder di search
      selectedClass: "selected",
      containerClass: "my-picker",
      hideOnSelect: true,
      fade: true,
      defaultValue: defaultIcon,
      valueFormat: (val) => `bi ${val}`,
    });

    // iconpicker.set(); // set empty
    // iconpicker.set("bi-alarm"); // set default value
  } catch (err) {
    console.error("Gagal memuat ikon:", err);
  }
}
/* document.addEventListener("click", function (e) {
  const simpanBtn = e.target.closest(".simpan");
  if (!simpanBtn) return; // Bukan tombol simpan

  e.preventDefault();

  const form = simpanBtn.closest("form");
  if (!form) {
    console.warn("⚠️ Form tidak ditemukan.");
    return;
  }

  const modal = simpanBtn.closest(".modal");
  const modalSelector = modal ? `#${modal.id}` : null;

  submitFormAjax(form, {
    modalSelector,
    callback: (res) => {
      if (res.group_id && typeof refreshGroupMenu === "function") {
        refreshGroupMenu(res.group_id);
      }

      if (res.data_optional) {
        console.log("Data opsional:", res.data_optional);
      }
    },
  });
}); */
