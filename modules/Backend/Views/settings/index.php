<!-- Default box -->
<div class="card">
    <div class="card-header d-flex justify-content-between ">
        <h3 class="card-title">
            <?= $label ?? 'Setting' ?>
        </h3>

    </div>

    <div class="card-body">
        <form
            action="<?= site_url('backend/setting/save') ?>"
            method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Tabs -->
            <ul class="nav nav-underline nav-justified" id="settingTab" role="tablist">
                <?php
                $settingsAssoc = [];
            foreach ($settingsByType as $groupSettings) {
                foreach ($groupSettings as $s) {
                    $settingsAssoc[$s['name']] = $s;
                }
            }
            ?>
                <?php $first = true;
            foreach ($settingsByType as $type => $settings): ?>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link <?= $first ? 'active' : '' ?>"
                        id="tab-<?= esc($type) ?>-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#content-<?= esc($type) ?>"
                        type="button" role="tab"
                        aria-controls="content-<?= esc($type) ?>"
                        aria-selected="<?= $first ? 'true' : 'false' ?>">
                        <?= ucfirst(($type)) ?>
                    </button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content mt-3" id="settingTabContent">
                <?php $first = true;
            foreach ($settingsByType as $type => $settings): ?>
                <div class="tab-pane fade <?= $first ? 'show active' : '' ?>"
                    id="content-<?= esc($type) ?>" role="tabpanel"
                    aria-labelledby="tab-<?= esc($type) ?>-tab">

                    <table class="table table-bordered table-hover table-sm my-settings table-head-fixed">
                        <thead>
                            <tr>
                                <th width="4%" class="text-center">No</th>
                                <th width="18%">Key</th>
                                <th width="62%">Name</th>
                                <th width="10%">Value</th>
                                <th width="6%">Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                foreach ($settings as $setting):
                    $fieldName          = esc($setting['name']);
                    $sortOrder          = esc($setting['sort_order']);
                    // $fieldValue         = esc($setting['value'] ?? '');
                    $rawValue           = $setting['value'] ?? '';
                    $fieldValue         = is_array($rawValue) ? (json_encode($rawValue) ?: '') : (string) $rawValue;
                    $inputType          = $setting['input_type'];
                    $options            = array_filter(array_map('trim', explode("\n", $setting['options'] ?? '')));
                    $isGoogleField      = in_array($fieldName, ['google_client_id', 'google_client_secret', 'google_redirect_URI']);
                    $googleLoginEnabled = ($settingsAssoc['google_login']['value'] ?? '') === 'true';
                    $isReadonly         = $isGoogleField && !$googleLoginEnabled ? 'readonly' : '';
                    $isDisabled         = $isGoogleField && !$googleLoginEnabled ? 'disabled' : '';
                    ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?>
                                </td>
                                <td>
                                    <?= esc($setting['label']) ?>
                                    <?php if (!empty($setting['help_text'])): ?>
                                    <br><small
                                        class="text-muted"><?= esc($setting['help_text']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inputType === 'input'): ?>
                                    <input type="text"
                                        name="value[<?= $fieldName ?>]"
                                        value="<?= $fieldValue ?>"
                                        class="form-control form-control-sm <?= esc($setting['class']) ?>"
                                        <?= $isReadonly ?>>

                                    <?php elseif ($inputType === 'textarea'): ?>
                                    <textarea
                                        name="value[<?= $fieldName ?>]"
                                        class="form-control form-control-sm summernote <?= esc($setting['class']) ?>"><?= sanitizeSettingHtml($fieldValue) ?></textarea>

                                    <?php elseif ($inputType === 'dropdown'): ?>
                                    <select
                                        name="value[<?= $fieldName ?>]"
                                        class="form-select form-select-sm <?= esc($setting['class']) ?>">
                                        <?php foreach ($options as $opt):
                                            list($optKey, $optLabel) = array_pad(explode('::', $opt), 2, $opt); ?>
                                        <option
                                            value="<?= esc($optKey) ?>"
                                            <?= $optKey == $fieldValue ? 'selected' : '' ?>>
                                            <?= esc($optLabel) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php elseif ($inputType === 'radio'): ?>
                                    <?php foreach ($options as $opt):
                                        list($optKey, $optLabel) = array_pad(explode('::', $opt), 2, $opt); ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio"
                                            id="<?= $fieldName ?>_<?= esc($optKey) ?>"
                                            name="value[<?= $fieldName ?>]"
                                            value="<?= esc($optKey) ?>"
                                            <?= $optKey == $fieldValue ? 'checked' : '' ?>>
                                        <label class="form-check-label"
                                            for="<?= $fieldName ?>_<?= esc($optKey) ?>">
                                            <?= esc($optLabel) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>

                                    <?php elseif ($inputType === 'checkbox'): ?>
                                    <?php
                                        $optionsChecked = array_filter(array_map('trim', explode("\n", $setting['options'] ?? '')));
                                        $checkedValue   = $optionsChecked[0] ?? 'true';
                                        $uncheckedValue = $optionsChecked[1] ?? 'false';
                                        ?>
                                    <input type="hidden"
                                        name="value[<?= $fieldName ?>]"
                                        value="<?= esc($uncheckedValue) ?>">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox"
                                            id="switch-<?= $fieldName ?>"
                                            name="value[<?= $fieldName ?>]"
                                            value="<?= esc($checkedValue) ?>"
                                            <?= $fieldValue == $checkedValue ? 'checked' : '' ?>>
                                        <label class="form-check-label"
                                            for="switch-<?= esc((string) $fieldName, 'attr') ?>">
                                            <?= esc(ucfirst((string) $fieldName)) ?>
                                        </label>
                                    </div>

                                    <?php elseif ($inputType === 'file'): ?>
                                    <?php
                                        // Pastikan variabel aman menjadi string dan tidak bernilai array
                                        $safeFieldValue = is_array($fieldValue) ? '' : (string) ($fieldValue ?? '');
                                        $safeFieldName  = is_array($fieldName) ? '' : (string) ($fieldName ?? '');
                                        ?>

                                    <div id="preview_<?= esc($safeFieldName) ?>"
                                        class="mb-2">
                                        <?php if (!empty($safeFieldValue) && preg_match('/\.(jpg|jpeg|png|gif|ico|webp|svg)$/i', $safeFieldValue)): ?>
                                        <img src="<?= base_url('uploads/settings/' . esc($safeFieldValue, 'url')) ?>"
                                            alt="Preview" style="max-height:80px;">
                                        <?php elseif (!empty($safeFieldValue)): ?>
                                        <small>File:
                                            <?= esc($safeFieldValue) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <input type="hidden"
                                        name="value[<?= esc($safeFieldName) ?>]"
                                        value="<?= esc($safeFieldValue, 'attr') ?>"
                                        id="hidden_<?= esc($safeFieldName) ?>">

                                    <button type="button" class="btn btn-sm btn-secondary"
                                        onclick="openUploadModal('<?= esc($safeFieldName, 'js') ?>')">
                                        Upload / Ganti File
                                    </button>
                                    <input type="hidden"
                                        name="value[<?= $fieldName ?>]"
                                        value="<?= $fieldValue ?>"
                                        id="hidden_<?= $fieldName ?>">


                                    <?php else: ?>
                                    <input type="text"
                                        name="value[<?= $fieldName ?>]"
                                        value="<?= $fieldValue ?>"
                                        class="form-control form-control-sm <?= esc($setting['class']) ?>">
                                    <?php endif; ?>
                                </td>
                                <td><code><?= $fieldName ?></code>
                                </td>
                                <td>
                                    <input type="text"
                                        name="sort_order[<?= $fieldName ?>]"
                                        value="<?= $sortOrder ?>"
                                        class="form-control form-control-sm text-center <?= esc($setting['class']) ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php $first = false; endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Simpan</button>
        </form>
    </div>
</div>
<?= $this->section('extraBottom') ?>
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="file" id="uploadInput" class="form-control mb-2">
                <input type="hidden" id="uploadSettingName">
                <div id="uploadPreview" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="uploadSettingFile()">Upload</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        /**
         * Fungsi utama untuk kontrol media sosial
         * @param {string} platform - nama platform, contoh: 'google', 'facebook'
         */
        function setupSocialMediaToggle(platform) {
            const radios = document.querySelectorAll(`input[name="value[${platform}_login]"]`);

            function toggleFields(isEnabled) {
                // Summernote fields
                // document.querySelectorAll(`.summernote[name^="value[${platform}_"]`).forEach(el => {
                //     if (isEnabled) {
                //         $(el).summernote("enable"); // tetap lewat jQuery wrapper
                //     } else {
                //         $(el).summernote("disable");
                //     }
                // });

                // Input & select fields (non-Summernote), kecuali tombol radio sendiri
                document.querySelectorAll(
                    `input[name^="value[${platform}_"], select[name^="value[${platform}_"]`).forEach(el => {
                    if (el.name !== `value[${platform}_login]` && !el.classList.contains(
                        "summernote")) {
                        el.disabled = !isEnabled;
                    }
                });
            }

            // Listener perubahan radio
            radios.forEach(radio => {
                radio.addEventListener("change", () => {
                    const isEnabled = document.querySelector(
                        `input[name="value[${platform}_login]"]:checked`).value === "true";
                    toggleFields(isEnabled);
                });
            });

            // Set kondisi awal
            const initial = document.querySelector(`input[name="value[${platform}_login]"]:checked`);
            toggleFields(initial && initial.value === "true");
        }

        // Panggil fungsi untuk tiap platform
        setupSocialMediaToggle("google");
        setupSocialMediaToggle("facebook");
        // setupSocialMediaToggle("twitter");

        // ------------------- Tab Persistence -------------------
        const settingTab = document.getElementById("settingTab");

        // Restore tab terakhir dari localStorage
        const activeTab = localStorage.getItem("activeSettingTab");
        if (activeTab) {
            const tabTrigger = document.querySelector(`#settingTab button[data-bs-target="${activeTab}"]`);
            if (tabTrigger) {
                new bootstrap.Tab(tabTrigger).show();
            }
        } else {
            // Default ke tab pertama
            const firstTab = document.querySelector('#settingTab button[data-bs-toggle="tab"]');
            if (firstTab) {
                new bootstrap.Tab(firstTab).show();
            }
        }

        // Simpan tab terakhir saat berpindah
        settingTab.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tabEl => {
            tabEl.addEventListener("shown.bs.tab", event => {
                localStorage.setItem("activeSettingTab", event.target.getAttribute(
                    "data-bs-target"));
            });
        });
    });

    // ------------------- Upload File Setting -------------------
    let currentSetting = "";

    function openUploadModal(settingName) {
        currentSetting = settingName;
        document.getElementById("uploadSettingName").value = settingName;
        document.getElementById("uploadInput").value = "";
        document.getElementById("uploadPreview").innerHTML = "";

        const uploadModal = new bootstrap.Modal(document.getElementById("uploadModal"));
        uploadModal.show();
    }

    // Preview saat pilih file
    document.getElementById("uploadInput").addEventListener("change", function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById("uploadPreview").innerHTML =
                    `<img src="${e.target.result}" style="max-height:100px;">`;
            };
            reader.readAsDataURL(file);
        }
    });

    // Fungsi upload pakai fetch
    async function uploadSettingFile() {
    const fileInput = document.getElementById("uploadInput");
    const settingName = document.getElementById("uploadSettingName")?.value;

    if (!fileInput || !fileInput.files.length) {
        alert("Pilih file dulu");
        return;
    }

    if (!settingName) {
        alert("Nama setting tidak ditemukan.");
        return;
    }

    const formData = new FormData();
    formData.append("file", fileInput.files[0]);
    formData.append("setting_name", settingName);

    try {
        const result = await window.apiFetch(
            "<?= site_url('backend/setting/upload') ?>",
            {
                method: "POST",
                body: formData
            }
        );

        // Respons JSON dari controller
        const response = result.data;

        if (result.ok && response.status === true) {
            const hiddenInput = document.getElementById(
                "hidden_" + currentSetting
            );
            const preview = document.getElementById(
                "preview_" + currentSetting
            );

            if (hiddenInput) {
                hiddenInput.value = response.filename;
            }

            if (preview) {
                if (/\.(jpg|jpeg|png|gif|webp)$/i.test(response.filename)) {
                    const img = document.createElement("img");
                    img.src = response.url;
                    img.alt = "Preview file";
                    img.style.maxHeight = "80px";

                    preview.replaceChildren(img);
                } else {
                    const small = document.createElement("small");
                    small.textContent = "File: " + response.filename;

                    preview.replaceChildren(small);
                }
            }

            const modalEl = document.getElementById("uploadModal");

            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }
            }
        } else {
            alert(
                "Upload gagal: " +
                (response.message || "Terjadi kesalahan saat upload.")
            );
        }
    } catch (error) {
        console.error("Upload setting error:", error);
        alert("Terjadi error saat upload. Periksa Console untuk detail.");
    }
}
</script>




<?= $this->endSection(); ?>
