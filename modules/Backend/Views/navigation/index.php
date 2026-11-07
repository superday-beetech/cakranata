<div class="row g-4">
    <!-- Kolom Kiri: Menu Section (Backend / Frontend) -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light py-2">
                <span class="small fw-semibold text-muted uppercase"><i class="bi bi-collection me-2"></i>
                    Segmen</span>
            </div>
            <div class="card-body p-2">
                <ul class="nav nav-pills flex-column gap-1" id="menuSectionTab">
                    <li class="nav-item">
                        <a class="nav-link fs-2 <?= ($section === 'backend') ? 'active' : '' ?>"
                            href="<?= base_url('backend/navigation?position=' . urlencode($position) . '&section=backend') ?>">
                            <i class="fas fa-cogs me-2"></i> Backend Menus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-2 <?= ($section === 'frontend') ? 'active' : '' ?>"
                            href="<?= base_url('backend/navigation?position=' . urlencode($position) . '&section=frontend') ?>">
                            <i class="fas fa-globe me-2"></i> Frontend Menus
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Kolom Kiri Bawah: Tab Posisi Menu (Sidebar / Topbar / Footer) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light py-2">
                <span class="small fw-semibold text-muted uppercase"><i class="bi bi-columns-gap me-2"></i> Posisi
                    Menu</span>
            </div>
            <div class="card-body p-2">
                <ul class="nav nav-pills flex-column gap-1" id="menuPositionTab">
                    <li class="nav-item">
                        <a class="nav-link fs-2 <?= ($position === 'sidebar') ? 'active fw-bold' : '' ?>"
                            href="<?= base_url('backend/navigation?position=sidebar&section=' . urlencode($section)) ?>">
                            <i class="bi bi-layout-sidebar-inset me-2"></i> Sidebar Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-2 <?= ($position === 'topbar') ? 'active fw-bold' : '' ?>"
                            href="<?= base_url('backend/navigation?position=topbar&section=' . urlencode($section)) ?>">
                            <i class="fas fa-window-maximize me-2"></i> Topbar Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-2 <?= ($position === 'down') ? 'active fw-bold' : '' ?>"
                            href="<?= base_url('backend/navigation?position=down&section=' . urlencode($section)) ?>">
                            <i class="fas fa-grip-lines me-2"></i> Down / Footer Menu
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Konten Utama / Struktur Menu (Drag & Drop) -->

    <!-- STRUKTUR MENU -->
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">

            <div class="card-header bg-primary py-2 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 text-white fs-3">
                    <i class="fas fa-sitemap me-1 text-dark"></i>
                    Struktur Menu (<?= esc(ucfirst($section)) ?> -
                    <?= esc(ucfirst($position)) ?>)
                </h6>

                <button type="button" class="btn btn-sm btn-light fw-medium" id="btn-add-menu"
                    data-bs-toggle="offcanvas" data-bs-target="#example2" aria-controls="example2">
                    <i class="fas fa-plus me-1"></i> Tambah Menu
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-warning fw-medium"
                    id="btn-sync-route-registry">
                    <i class="fas fa-sync-alt me-1"></i>
                    Sinkronisasi Route Registry
                </button>
                <button class="btn btn-link btn-sm d-none" type="button" disabled="" id="btn-add-loading">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </button>
            </div>

            <div class="card-body p-3">

                <div class="alert alert-light-primary bg-primary-subtle small py-2" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-info-circle fs-5 me-2 text-primary"></i>
                        Geser menu ke atas/bawah atau masukkan ke dalam menu lain
                        untuk mengatur struktur hierarki.
                    </div>
                </div>

                <ul class="menu-list" id="sortable-menu">
                    <?php
                    /*
                     * Render menu secara rekursif.
                     * Variabel $menus diharapkan berisi seluruh menu
                     * pada section dan position yang sedang dipilih.
                     */
                    $renderAdjacencySortable = function ($items, $parentId = 0) use (&$renderAdjacencySortable) {
                        foreach ($items as $m) {
                            if ((string) ($m['parent_id'] ?? 0) !== (string) $parentId) {
                                continue;
                            }

                            $menuId = (int) $m['id'];
                            $title = (string) ($m['title'] ?? '');
                            $url = (string) ($m['url'] ?? '');
                            $icon = (string) ($m['icon'] ?? '');
                            $isActive = (string) ($m['is_active'] ?? '0');
                            $menuParentId = (int) ($m['parent_id'] ?? 0);

                            $selectedGroups = array_values(
                                $m['selected_groups'] ?? []
                            );

                            $groupsJson = json_encode(
                                $selectedGroups,
                                JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_TAG |
                                    JSON_HEX_AMP
                            );

                            $hasChildren = false;

                            foreach ($items as $sub) {
                                if ((string) ($sub['parent_id'] ?? 0) === (string) $menuId) {
                                    $hasChildren = true;
                                    break;
                                }
                            }
                    ?>
                            <li class="menu-item mb-2"
                                data-id="<?= $menuId ?>">
                                <div class="menu-card p-2 rounded d-flex justify-content-between align-items-center shadow-sm handle"
                                    style="cursor: grab;">

                                    <div class="d-flex align-items-center flex-wrap">
                                        <i
                                            class="<?= esc($icon, 'attr') ?> text-secondary me-2"></i>

                                        <span class="small text-dark">
                                            <?= esc($title) ?>
                                        </span>

                                        <small class="text-muted ms-2">
                                            (<?= esc($url) ?>)
                                        </small>

                                        <span class="badge bg-primary ms-2 menu-counter" style="font-size: .7rem;">
                                            0
                                        </span>
                                    </div>

                                    <div class="btn-group btn-group-sm flex-shrink-0">

                                        <!-- EDIT -->
                                        <button type="button" class="btn btn-link border-0 btn-edit py-0 px-1"
                                            data-bs-toggle="offcanvas" data-bs-target="#example2" aria-controls="example2"
                                            data-id="<?= $menuId ?>"
                                            data-title="<?= esc($title, 'attr') ?>"
                                            data-url="<?= esc($url, 'attr') ?>"
                                            data-icon="<?= esc($icon, 'attr') ?>"
                                            data-is-active="<?= esc($isActive, 'attr') ?>"
                                            data-parent-id="<?= $menuParentId ?>"
                                            data-groups="<?= esc($groupsJson, 'attr') ?>"
                                            aria-label="Edit menu <?= esc($title, 'attr') ?>"
                                            title="Edit menu">
                                            <i class="bi bi-pen"></i>
                                        </button>

                                        <!-- DELETE -->
                                        <a href="<?= base_url('backend/navigation/delete/' . $menuId) ?>"
                                            class="btn btn-link border-0 py-0 px-1" title="Hapus menu"
                                            onclick="return confirm('Hapus menu ini beserta seluruh sub-menu di dalamnya?')">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </div>
                                </div>

                                <!-- SUBMENU -->
                                <ul class="menu-sublist">
                                    <?php
                                    if ($hasChildren) {
                                        $renderAdjacencySortable($items, $menuId);
                                    }
                                    ?>
                                </ul>
                            </li>
                    <?php
                        }
                    };

                    $renderAdjacencySortable($menus, 0);
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- OFFCANVAS FORM -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="example2" aria-labelledby="form-title">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="form-title">
            <i class="fas fa-plus-circle me-1"></i>
            Tambah Menu Baru
        </h5>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup">
        </button>
    </div>

    <div class="offcanvas-body">
        <form
            action="<?= base_url('backend/navigation/save') ?>"
            method="post" id="menu-form">

            <?= csrf_field('csrf-input') ?>

            <input type="hidden" name="id" id="menu-id">

            <input type="hidden" name="position"
                value="<?= esc($position, 'attr') ?>">

            <input type="hidden" name="section"
                value="<?= esc($section, 'attr') ?>">

            <div class="form-floating mb-3">

                <input type="text" name="title" id="title" class="form-control form-control-sm" required maxlength="150"
                    placeholder="Contoh: Manajemen Produk">
                <label for="title" class="form-label fw-medium small">
                    <i class="bi bi-card-heading me-2 fs-4 text-info"></i> Judul Menu
                </label>
            </div>

            <div class="form-floating mb-3">

                <input type="text" name="url" id="url" class="form-control form-control-sm" required maxlength="255"
                    placeholder="Contoh: backend/products">
                <label for="url" class="form-label fw-medium small">
                    <i class="bi bi-link me-2 fs-4 text-info"></i> URL / Route Target
                </label>
            </div>

            <div class="mb-3 form-floating">
                <input type="text" name="icon" id="icon" class="form-control form-control-sm"
                    placeholder="Contoh: fas fa-box">
                <label for="icon" class="form-label fw-medium small">
                    <i class="bi bi-info me-2 fs-4 text-info"></i> Icon
                </label>
            </div>

            <div class="mb-3 form-floating d-none">
                <input type="text" name="parent_id" id="parent_id" class="form-control form-control-sm" readonly
                    placeholder="0">
                <label for="parent_id" class="form-label fw-medium small">
                    <i class="bi bi-diagram-2 me-2 fs-4 text-info"></i> Parent ID
                </label>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium small">
                    Hak Akses Group / Role
                </label>

                <div class="border p-2 rounded bg-primary-subtle" style="max-height: 180px; overflow-y: auto;">

                    <?php foreach ($roles as $role): ?>
                        <div class="form-check">
                            <input type="checkbox" name="groups[]"
                                value="<?= esc($role, 'attr') ?>"
                                class="form-check-input role-checkbox"
                                id="role_<?= esc($role, 'attr') ?>">

                            <label class="form-check-label text-capitalize small"
                                for="role_<?= esc($role, 'attr') ?>">
                                <?= esc($role) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-text text-muted">
                    Pilih role yang diizinkan mengakses menu ini.
                </div>
            </div>

            <div class="mb-3 form-check form-switch">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" checked>

                <label class="form-check-label small" for="is_active">
                    Status Aktif
                </label>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-sm" id="btn-save-menu">
                    <i class="fas fa-save me-1"></i>
                    Simpan Menu
                </button>

                <button type="button" id="btn-cancel" class="btn btn-outline-secondary btn-sm d-none">
                    <i class="bi bi-x-circle me-1 text-info"></i> Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="autoSaveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive"
        aria-atomic="true">

        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>

            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close">
            </button>
        </div>
    </div>
</div>


<div class="modal fade" id="routeRegistryImportModal" tabindex="-1"
    aria-labelledby="routeRegistryImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="routeRegistryImportModalLabel">
                        Sinkronisasi Menu
                    </h5>
                    <small class="text-muted">
                        Pilih kandidat route dan tentukan grup yang dapat mengaksesnya.
                    </small>
                </div>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div id="route-import-status"
                    class="alert alert-info py-2" role="status">
                    Memuat data...
                </div>

                <div class="row g-3">

                    <div class="col-lg-7">
                        <h6>Kandidat dari Route Registry</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:35px">Pilih</th>
                                        <th>Menu / URL</th>
                                        <th style="min-width:115px">Posisi</th>
                                        <th style="min-width:180px">Grup Akses</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="route-import-candidates">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Memuat...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <h6>Menu yang Sudah Ada</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Menu</th>
                                        <th>URL</th>
                                    </tr>
                                </thead>
                                <tbody id="route-import-existing">
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">
                                            Memuat...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="button" class="btn btn-primary"
                    id="btn-save-route-import" disabled>
                    <i class="fas fa-save me-1"></i> Simpan Menu Terpilih
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->section('jsFooterInline') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        'use strict';

        // =========================================================
        // 1. REFERENSI ELEMEN
        // =========================================================

        const rootMenu = document.getElementById('sortable-menu');

        const form = document.getElementById('menu-form');
        const offcanvasElement = document.getElementById('example2');
        const addButton = document.getElementById('btn-add-menu');
        const addButtonLoading = document.getElementById('btn-add-loading');
        const cancelButton = document.getElementById('btn-cancel');

        const menuId = document.getElementById('menu-id');
        const titleInput = document.getElementById('title');
        const urlInput = document.getElementById('url');
        const iconInput = document.getElementById('icon');
        const parentInput = document.getElementById('parent_id');
        const activeToggle = document.getElementById('is_active');
        const formTitle = document.getElementById('form-title');

        const offcanvas = offcanvasElement && window.bootstrap ?
            bootstrap.Offcanvas.getOrCreateInstance(offcanvasElement) :
            null;

        // =========================================================
        // 2. TOAST / ALERT
        // =========================================================

        function showToast(message, type = 'success') {
            const toastEl = document.getElementById('autoSaveToast');
            const toastMessage = document.getElementById('toastMessage');

            if (!toastEl || !toastMessage || !window.bootstrap) {
                if (typeof window.showAlert === 'function') {
                    window.showAlert(message, type === 'success' ? 'success' : 'danger');
                } else {
                    console.log(message);
                }
                return;
            }

            toastEl.className =
                'toast align-items-center text-white border-0 ' +
                (type === 'success' ? 'bg-success' : 'bg-danger');

            toastMessage.textContent = message;

            bootstrap.Toast.getOrCreateInstance(toastEl, {
                delay: 3000
            }).show();
        }

        // =========================================================
        // 3. FORM TAMBAH / EDIT
        // =========================================================

        function resetMenuForm() {
            if (!form) {
                return;
            }

            form.reset();

            menuId.value = '';
            parentInput.value = '';
            activeToggle.checked = true;

            form.querySelectorAll('input[name="groups[]"]').forEach(
                checkbox => {
                    checkbox.checked = false;
                }
            );

            formTitle.innerHTML =
                '<i class="fas fa-plus-circle me-1"></i> Tambah Menu Baru';

            cancelButton?.classList.add('d-none');
        }

        function populateEditForm(button) {
            resetMenuForm();

            menuId.value = button.dataset.id || '';
            titleInput.value = button.dataset.title || '';
            urlInput.value = button.dataset.url || '';
            iconInput.value = button.dataset.icon || '';
            parentInput.value = button.dataset.parentId || '';

            activeToggle.checked = button.dataset.isActive === '1';

            let selectedGroups = [];

            try {
                const parsedGroups = JSON.parse(
                    button.dataset.groups || '[]'
                );

                if (Array.isArray(parsedGroups)) {
                    selectedGroups = parsedGroups;
                }
            } catch (error) {
                console.error('Data groups menu tidak valid:', error);
                showToast('Data role menu tidak dapat dibaca.', 'error');
            }

            selectedGroups.forEach(function(role) {
                const checkbox = document.getElementById(
                    'role_' + String(role)
                );

                if (checkbox) {
                    checkbox.checked = true;
                }
            });

            formTitle.innerHTML =
                '<i class="fas fa-edit me-1"></i> Edit Menu';

            cancelButton?.classList.remove('d-none');
        }

        if (form && offcanvasElement && offcanvas) {

            addButton?.addEventListener('click', function() {
                resetMenuForm();
                addButtonLoading.classList.remove('d-none');
                addButton.classList.add('d-none');
                // btn-add-loading
            });

            // Delegasi event supaya tombol Edit tetap berfungsi
            // jika daftar menu nantinya diperbarui melalui JavaScript.
            document.addEventListener('click', function(event) {
                const button = event.target.closest('.btn-edit');

                if (!button) {
                    return;
                }

                populateEditForm(button);
            });

            cancelButton?.addEventListener('click', function() {
                resetMenuForm();
                addButtonLoading.classList.add('d-none');
                offcanvas.hide();
            });

            offcanvasElement.addEventListener(
                'hidden.bs.offcanvas',
                resetMenuForm
            );

            offcanvasElement.addEventListener('show.bs.offcanvas', () => {
                addButtonLoading.classList.remove('d-none');
                addButton.classList.add('d-none');
            });

            offcanvasElement.addEventListener('hidden.bs.offcanvas', () => {
                addButtonLoading.classList.add('d-none');
                addButton.classList.remove('d-none');
            });

        } else {
            console.error('Form menu atau Bootstrap Offcanvas tidak ditemukan.');
        }

        // =========================================================
        // 4. SORTABLEJS: HIERARKI MENU
        // =========================================================

        function initSortable(container) {
            if (!container || !window.Sortable) {
                return;
            }

            new Sortable(container, {
                group: 'nested-menu',
                animation: 150,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                handle: '.handle',

                onEnd: function() {
                    updateCountersAndEmptyStates();
                    autoSaveMenuOrder();
                }
            });

            // Inisialisasi hanya submenu langsung setiap item,
            // supaya instance Sortable tidak dibuat dua kali.
            Array.from(container.children).forEach(function(item) {
                if (!item.classList.contains('menu-item')) {
                    return;
                }

                const subList = item.querySelector(
                    ':scope > .menu-sublist'
                );

                if (subList) {
                    initSortable(subList);
                }
            });
        }

        if (rootMenu) {
            initSortable(rootMenu);
        } else {
            console.error('Elemen #sortable-menu tidak ditemukan.');
        }

        // =========================================================
        // 5. COUNTER JUMLAH SUBMENU
        // =========================================================

        function updateCountersAndEmptyStates() {
            if (!rootMenu) {
                return;
            }

            rootMenu.querySelectorAll('.menu-item').forEach(function(item) {
                const badge = item.querySelector(
                    ':scope > .menu-card .menu-counter'
                );

                const subList = item.querySelector(
                    ':scope > .menu-sublist'
                );

                if (!subList) {
                    return;
                }

                const count = Array.from(subList.children).filter(
                    child => child.classList.contains('menu-item')
                ).length;

                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }

                const placeholder = subList.querySelector(
                    ':scope > .empty-placeholder'
                );

                if (placeholder) {
                    placeholder.style.display =
                        count === 0 ? 'block' : 'none';
                }
            });
        }

        updateCountersAndEmptyStates();

        // =========================================================
        // 6. SERIALISASI HIERARKI MENU
        // =========================================================

        function serializeMenu(container, parentId = 0) {
            const data = [];
            let sequence = 1;

            const items = Array.from(container.children).filter(
                item => item.classList.contains('menu-item')
            );

            items.forEach(function(item) {
                const id = Number.parseInt(item.dataset.id, 10);

                if (!Number.isInteger(id) || id <= 0) {
                    return;
                }

                const node = {
                    id: id,
                    parent_id: parentId,
                    sequence: sequence++
                };

                const subList = item.querySelector(
                    ':scope > .menu-sublist'
                );

                node.children = subList ?
                    serializeMenu(subList, id) : [];

                data.push(node);
            });

            return data;
        }

        // =========================================================
        // 7. AUTOSAVE URUTAN MENU
        // =========================================================

        let saveInProgress = false;
        let saveQueued = false;

        async function autoSaveMenuOrder() {
            if (!rootMenu) {
                return;
            }

            // Jika masih ada request, tandai agar kondisi terbaru
            // dikirim lagi setelah request sebelumnya selesai.
            if (saveInProgress) {
                saveQueued = true;
                return;
            }

            saveInProgress = true;

            try {
                do {
                    saveQueued = false;

                    const hierarchyData = serializeMenu(rootMenu, 0);
                    // console.log(typeof window.apiFetch);
                    if (typeof window.apiFetch !== 'function') {
                        throw new Error('Wrapper apiFetch tidak tersedia.');
                    }

                    const result = await window.apiFetch(
                        "<?= base_url('backend/navigation/updateOrder') ?>", {
                            method: 'POST',
                            body: hierarchyData
                        }
                    );

                    if (!result || !result.data ||
                        result.data.status !== 'success') {
                        const message = result?.data?.message ||
                            'Gagal menyimpan urutan menu.';

                        throw new Error(message);
                    }

                    if (!saveQueued) {
                        showToast('Urutan menu berhasil disimpan.', 'success');
                    }

                } while (saveQueued);

            } catch (error) {
                console.error('Auto-save menu error:', error);
                showToast(
                    error.message || 'Terjadi kesalahan saat menyimpan urutan.',
                    'error'
                );

            } finally {
                saveInProgress = false;
            }
        }

    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const previewUrl = <?= json_encode(base_url('backend/navigation/syncMenuPreview')) ?>;
        const saveUrl = <?= json_encode(base_url('backend/navigation/syncMenuSave')) ?>;
        const currentSection = <?= json_encode($section ?? 'backend') ?>;

        const modalElement = document.getElementById('routeRegistryImportModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        const openButton = document.getElementById('btn-sync-route-registry');
        const saveButton = document.getElementById('btn-save-route-import');
        const statusElement = document.getElementById('route-import-status');
        const existingBody = document.getElementById('route-import-existing');
        const candidateBody = document.getElementById('route-import-candidates');

        if (!openButton || !saveButton || !modalElement) return;

        let loading = false;

        function setStatus(message, type = 'info') {
            statusElement.className = `alert alert-${type} py-2`;
            statusElement.textContent = message;
        }

        function createCell(text, className = '') {
            const td = document.createElement('td');
            td.textContent = text ?? '';
            if (className) td.className = className;
            return td;
        }

        function createEmptyRow(colspan, message) {
            const tr = document.createElement('tr');
            const td = createCell(message, 'text-center text-muted py-3');
            td.colSpan = colspan;
            tr.appendChild(td);
            return tr;
        }

        function createCheckbox(labelText, value, name, checked = false) {
            const wrapper = document.createElement('label');
            wrapper.className = 'd-flex align-items-center gap-1 mb-1 small';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = name;
            input.value = String(value);
            input.checked = checked;

            const label = document.createElement('span');
            label.textContent = labelText;

            wrapper.append(input, label);
            return {
                wrapper,
                input
            };
        }

        function renderExisting(menus) {
            existingBody.replaceChildren();

            if (!menus.length) {
                existingBody.appendChild(createEmptyRow(2, 'Belum ada menu.'));
                return;
            }

            menus.forEach(menu => {
                const tr = document.createElement('tr');
                tr.append(
                    createCell(menu.title),
                    createCell(menu.url || '-')
                );
                existingBody.appendChild(tr);
            });
        }

        function renderCandidates(candidates, groups) {
            candidateBody.replaceChildren();

            if (!candidates.length) {
                candidateBody.appendChild(
                    createEmptyRow(5, 'Tidak ada kandidat di route_registry_menus.')
                );
                return;
            }

            candidates.forEach(candidate => {
                const tr = document.createElement('tr');
                tr.dataset.routeMenuId = String(candidate.id);

                const selectCell = document.createElement('td');
                const select = document.createElement('input');
                select.type = 'checkbox';
                select.className = 'form-check-input js-route-select';
                select.setAttribute('aria-label', `Pilih ${candidate.title}`);
                select.disabled = Boolean(candidate.exists);
                selectCell.appendChild(select);

                const titleCell = document.createElement('td');
                const title = document.createElement('div');
                title.className = 'fw-semibold';
                title.textContent = candidate.title;

                const url = document.createElement('small');
                url.className = 'text-muted text-break';
                url.textContent = candidate.url || '(URL kosong)';

                titleCell.append(title, url);

                const positionCell = document.createElement('td');
                const position = document.createElement('select');
                position.className = 'form-select form-select-sm js-route-position';

                [
                    ['sidebar', 'Sidebar'],
                    ['topbar', 'Topbar'],
                    ['down', 'Down']
                ].forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    position.appendChild(option);
                });

                positionCell.appendChild(position);

                const groupCell = document.createElement('td');
                const groupContainer = document.createElement('div');
                groupContainer.className = 'd-flex flex-column';

                groups.forEach(group => {
                    const checkbox = createCheckbox(
                        group.name,
                        group.name,
                        `route-groups-${candidate.id}[]`
                    );
                    checkbox.input.classList.add('js-route-group');
                    groupContainer.appendChild(checkbox.wrapper);
                });

                groupCell.appendChild(groupContainer);

                const statusCell = document.createElement('td');
                const badge = document.createElement('span');

                if (candidate.exists) {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'Sudah ada';
                } else if (!candidate.url) {
                    badge.className = 'badge bg-danger';
                    badge.textContent = 'URL kosong';
                    select.disabled = true;
                } else {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Baru';
                }

                statusCell.appendChild(badge);

                select.addEventListener('change', () => {
                    tr.classList.toggle('table-primary', select.checked);
                });

                tr.append(
                    selectCell,
                    titleCell,
                    positionCell,
                    groupCell,
                    statusCell
                );

                candidateBody.appendChild(tr);
            });
        }

        async function loadPreview() {
            if (loading) return;
            loading = true;
            saveButton.disabled = true;

            setStatus('Memuat menu dan kandidat route...', 'info');

            try {
                const result = await window.apiFetch(previewUrl, {
                    method: 'POST',
                    body: {
                        section: currentSection
                    }
                });

                if (!result?.data || result.data.status !== 'success') {
                    throw new Error(
                        result?.data?.message || 'Gagal mengambil data sinkronisasi.'
                    );
                }

                const data = result.data.data || {};

                renderExisting(data.menus || []);
                renderCandidates(data.candidates || [], data.groups || []);

                const count = (data.candidates || []).filter(item =>
                    !item.exists && item.url
                ).length;

                setStatus(
                    `Tersedia ${count} kandidat yang belum terdaftar.`,
                    'info'
                );

                saveButton.disabled = count === 0;
            } catch (error) {
                setStatus(error.message || 'Gagal memuat data.', 'danger');
                existingBody.replaceChildren(createEmptyRow(2, 'Gagal memuat data.'));
                candidateBody.replaceChildren(createEmptyRow(5, 'Gagal memuat data.'));
            } finally {
                loading = false;
            }
        }

        openButton.addEventListener('click', async function() {
            modal.show();
            await loadPreview();
        });

        saveButton.addEventListener('click', async function() {
            const selectedRows = Array.from(
                candidateBody.querySelectorAll('tr')
            ).filter(row => row.querySelector('.js-route-select')?.checked);

            if (!selectedRows.length) {
                setStatus('Pilih minimal satu kandidat menu.', 'warning');
                return;
            }

            /*             const items = selectedRows.map(row => ({
                            route_menu_id: Number(row.dataset.routeMenuId),
                            position: row.querySelector('.js-route-position').value,
                            groups: Array.from(
                                row.querySelectorAll('.js-route-group:checked')
                            ).map(input => Number(input.value))
                        }));
             */
            const items = selectedRows.map(row => ({
                route_menu_id: Number(row.dataset.routeMenuId),
                position: row.querySelector('.js-route-position').value,
                groups: Array.from(
                    row.querySelectorAll('.js-route-group:checked')
                ).map(input => input.value)
            }));

            saveButton.disabled = true;
            saveButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

            try {
                const result = await window.apiFetch(saveUrl, {
                    method: 'POST',
                    body: {
                        section: currentSection,
                        items
                    }
                });

                if (!result?.data || result.data.status !== 'success') {
                    throw new Error(
                        result?.data?.message || 'Sinkronisasi gagal.'
                    );
                }

                const data = result.data.data || {};
                const inserted = (data.inserted || []).length;
                const skipped = (data.skipped || []).length;

                setStatus(
                    `Berhasil menambahkan ${inserted} menu. ` +
                    `${skipped} kandidat dilewati karena URL sudah ada.`,
                    'success'
                );

                // Perbarui preview agar menu baru langsung terlihat.
                await loadPreview();

                // Pertahankan hasil ringkasan setelah preview dimuat ulang.
                setStatus(
                    `Sinkronisasi selesai: ${inserted} menu ditambahkan, ` +
                    `${skipped} dilewati karena duplikasi URL.`,
                    'success'
                );

                // Refresh halaman supaya struktur menu utama ikut diperbarui.
                if (inserted > 0) {
                    window.setTimeout(() => window.location.reload(), 1200);
                }
            } catch (error) {
                setStatus(error.message || 'Sinkronisasi gagal.', 'danger');
            } finally {
                saveButton.innerHTML =
                    '<i class="fas fa-save me-1"></i> Simpan Menu Terpilih';
                saveButton.disabled = !candidateBody.querySelector(
                    '.js-route-select:not(:disabled)'
                );
            }
        });
    });
</script>
<?= $this->endSection() ?>
