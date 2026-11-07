<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="mb-1">Permission Management</h4>
            <p class="text-muted mb-0">
                Kelola nama dan deskripsi permission aplikasi.
            </p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="btnRefresh">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="btnCreate">
                <i class="bi bi-plus-lg"></i> Tambah Permission
            </button>
        </div>
    </div>

    <div id="permissionAlert" class="alert d-none" role="alert"></div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-center mb-3">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="search"
                            id="permissionSearch"
                            class="form-control"
                            placeholder="Cari nama atau deskripsi..."
                            autocomplete="off">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="btnSearch">Cari</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-8 text-md-end">
                    <label for="perPage" class="form-label me-2 mb-0">
                        Baris per halaman
                    </label>
                    <select id="perPage" class="form-select d-inline-block" style="width: auto">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px">No.</th>
                            <th>Nama Permission</th>
                            <th>Deskripsi</th>
                            <th style="width: 170px">Dibuat</th>
                            <th class="text-end" style="width: 130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="permissionTableBody">
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <small class="text-muted" id="permissionSummary">
                    Memuat data...
                </small>

                <nav aria-label="Pagination permission">
                    <ul class="pagination pagination-sm mb-0" id="permissionPagination"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <strong>Permission yang perlu ditambahkan</strong>
            <div class="text-muted small">
                Pilih permission yang sudah dipetakan di Route Registry
                tetapi belum tersedia di Shield.
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                id="btnSelectMissing">
                Pilih Semua
            </button>
            <button type="button" class="btn btn-sm btn-primary"
                id="btnSyncPermissions" disabled>
                Sinkronkan Terpilih
            </button>
        </div>
    </div>

    <div id="registrySyncNotice" class="alert d-none" role="alert"></div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Route Registry Sync</h5>
                <small class="text-muted">
                    Bandingkan method terdaftar dengan permission aplikasi.
                </small>
            </div>

            <button type="button" class="btn btn-outline-primary"
                id="btnLoadRegistry">
                <i class="bi bi-arrow-repeat"></i> Muat & Bandingkan
            </button>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <small class="text-muted">Method Terdaftar</small>
                        <h4 id="registryTotal" class="mb-0">—</h4>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <small class="text-muted">Permission Sudah Ada</small>
                        <h4 id="registryMatched" class="mb-0">—</h4>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <small class="text-muted">Perlu Ditinjau</small>
                        <h4 id="registryMissing" class="mb-0">—</h4>
                    </div>
                </div>
            </div>

            <div id="registryAlert" class="alert d-none" role="alert"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Module</th>
                            <th>Controller / Method</th>
                            <th>Permission Registry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="registryTableBody">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                Klik Muat & Bandingkan untuk melihat hasil.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah/edit -->
<div class="modal fade" id="permissionModal" tabindex="-1"
    aria-labelledby="permissionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="permissionForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissionModalTitle">
                        Tambah Permission
                    </h5>
                    <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="permissionId">

                    <div class="mb-3">
                        <label for="permissionName" class="form-label">
                            Nama Permission <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="permissionName"
                            maxlength="150"
                            placeholder="backend.navigation.view"
                            required>
                        <div class="form-text">
                            Gunakan format module.resource.action.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="permissionDescription" class="form-label">
                            Deskripsi
                        </label>
                        <textarea
                            class="form-control"
                            id="permissionDescription"
                            rows="3"
                            maxlength="255"
                            placeholder="Penjelasan fungsi permission"></textarea>
                    </div>

                    <div id="permissionFormError"
                        class="alert alert-danger d-none mb-0"
                        role="alert"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSavePermission">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal konfirmasi hapus -->
<div class="modal fade" id="deletePermissionModal" tabindex="-1"
    aria-labelledby="deletePermissionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePermissionTitle">
                    Hapus Permission
                </h5>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Apakah kamu yakin ingin menghapus permission ini?</p>
                <code id="deletePermissionName"></code>
                <p class="text-muted small mt-3 mb-0">
                    Permission yang masih digunakan akan ditolak oleh server.
                </p>
                <div id="deletePermissionError"
                    class="alert alert-danger d-none mt-3 mb-0"
                    role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    Hapus
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->section('jsFooterInline'); ?>
<script>
    (() => {
        'use strict';

        // Sesuaikan hanya bila base API yang sudah digunakan proyek berbeda.
        const API_BASE = <?= json_encode(
                                rtrim($apiBase ?? base_url('api/v1/rbac/permissions'), '/'),
                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                            ) ?>;

        const $ = (id) => document.getElementById(id);

        const state = {
            page: 1,
            perPage: 20,
            search: '',
            totalPages: 1,
            items: [],
            editingId: null,
            deletingId: null,
            loading: false
        };

        const modalElement = $('permissionModal');
        const deleteModalElement = $('deletePermissionModal');

        const permissionModal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalElement);

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        function notify(message, type = 'success') {
            const alert = $('permissionAlert');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.classList.remove('d-none');

            if (type === 'success') {
                window.setTimeout(() => alert.classList.add('d-none'), 4000);
            }
        }

        function showFormError(message) {
            const element = $('permissionFormError');
            element.textContent = message;
            element.classList.remove('d-none');
        }

        function hideFormError() {
            $('permissionFormError').classList.add('d-none');
            $('permissionFormError').textContent = '';
        }

        async function request(url, options = {}) {
            if (typeof window.apiFetch !== 'function') {
                throw new Error('window.apiFetch belum tersedia.');
            }

            const response = await window.apiFetch(url, options);

            // Mendukung Response Fetch standar.
            if (response && typeof response.json === 'function') {
                const body = await response.json();

                if (!response.ok || body.error) {
                    throw new Error(body.message || 'Permintaan gagal.');
                }

                return body;
            }

            // Mendukung apiFetch yang sudah mengembalikan JSON.
            if (response && typeof response === 'object') {
                if (response.error) {
                    throw new Error(response.message || 'Permintaan gagal.');
                }

                return response;
            }

            throw new Error('Respons API tidak valid.');
        }

        function renderRows(items) {
            const tbody = $('permissionTableBody');

            if (!items.length) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        Tidak ada permission ditemukan.
                    </td>
                </tr>`;
                return;
            }

            const firstNumber = (state.page - 1) * state.perPage;

            tbody.innerHTML = items.map((item, index) => `
            <tr>
                <td>${firstNumber + index + 1}</td>
                <td>
                    <code>${escapeHtml(item.name)}</code>
                </td>
                <td>${escapeHtml(item.description || '—')}</td>
                <td>${escapeHtml(item.created_at || '—')}</td>
                <td class="text-end">
                    <button type="button"
                        class="btn btn-sm btn-outline-primary me-1"
                        data-action="edit"
                        data-id="${Number(item.id)}"
                        title="Edit deskripsi"
                        aria-label="Edit deskripsi">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        data-action="delete"
                        data-id="${Number(item.id)}"
                        title="Hapus permission"
                        aria-label="Hapus permission">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        }

        function renderPagination() {
            const pagination = $('permissionPagination');
            const totalPages = Math.max(1, state.totalPages);
            const current = state.page;

            const button = (label, page, disabled, active = false) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <button type="button" class="page-link"
                    data-page="${page}" ${disabled ? 'disabled' : ''}>
                    ${label}
                </button>
            </li>`;

            let html = button('&laquo;', current - 1, current <= 1);

            let start = Math.max(1, current - 2);
            let end = Math.min(totalPages, current + 2);

            if (end - start < 4) {
                start = Math.max(1, end - 4);
                end = Math.min(totalPages, start + 4);
            }

            for (let page = start; page <= end; page++) {
                html += button(page, page, false, page === current);
            }

            html += button('&raquo;', current + 1, current >= totalPages);

            pagination.innerHTML = html;
        }

        async function loadPermissions() {
            if (state.loading) return;

            state.loading = true;
            $('btnRefresh').disabled = true;

            $('permissionTableBody').innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Memuat data...
                </td>
            </tr>`;

            try {
                const params = new URLSearchParams({
                    page: String(state.page),
                    per_page: String(state.perPage),
                    search: state.search
                });

                const result = await request(`${API_BASE}/getIndex?${params.toString()}`);

                const data = result.data.data ?? {};

                state.items = Array.isArray(data.items) ? data.items : [];
                state.totalPages = Math.max(1, Number(data.total_pages || 1));

                // Jaga halaman tetap valid jika data terakhir dihapus.
                if (state.page > state.totalPages) {
                    state.page = state.totalPages;
                    state.loading = false;
                    return loadPermissions();
                }

                renderRows(state.items);
                renderPagination();

                const total = Number(data.total || 0);
                $('permissionSummary').textContent =
                    `Total ${total} permission · Halaman ${state.page} dari ${state.totalPages}`;

            } catch (error) {
                $('permissionTableBody').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>`;

                notify(error.message, 'danger');
            } finally {
                state.loading = false;
                $('btnRefresh').disabled = false;
            }
        }

        function openCreateModal() {
            state.editingId = null;
            $('permissionForm').reset();
            $('permissionId').value = '';
            $('permissionName').disabled = false;
            $('permissionModalTitle').textContent = 'Tambah Permission';
            $('btnSavePermission').textContent = 'Simpan';
            hideFormError();
            permissionModal.show();
        }

        async function openEditModal(id) {
            hideFormError();

            try {
                const result = await request(`${API_BASE}/show/${id}`);
                const item = result.data.data?.item ?? result.data.data ?? result;
                if (!item) {
                    throw new Error('Data permission tidak ditemukan.');
                }

                state.editingId = Number(item.id);
                $('permissionId').value = item.id;
                $('permissionName').value = item.name || '';
                $('permissionName').disabled = true;
                $('permissionDescription').value = item.description || '';
                $('permissionModalTitle').textContent = 'Edit Deskripsi Permission';
                $('btnSavePermission').textContent = 'Simpan Perubahan';

                permissionModal.show();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }

        async function savePermission(event) {
            event.preventDefault();
            hideFormError();

            const isEditing = state.editingId !== null;
            const payload = {
                description: $('permissionDescription').value.trim()
            };

            if (!isEditing) {
                payload.name = $('permissionName').value.trim();
            }
            console.log(isEditing);
            const button = $('btnSavePermission');
            button.disabled = true;
            button.textContent = 'Menyimpan...';

            try {
                await request(
                    isEditing ? `${API_BASE}/update/${state.editingId}` : API_BASE + 'create', {
                        method: isEditing ? 'PUT' : 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    }
                );

                permissionModal.hide();
                notify(
                    isEditing ?
                    'Deskripsi permission berhasil diperbarui.' :
                    'Permission berhasil ditambahkan.'
                );

                await loadPermissions();
            } catch (error) {
                showFormError(error.message);
            } finally {
                button.disabled = false;
                button.textContent = isEditing ? 'Simpan Perubahan' : 'Simpan';
            }
        }

        function openDeleteModal(id) {
            const item = state.items.find((row) => Number(row.id) === Number(id));

            if (!item) {
                notify('Data permission tidak ditemukan pada halaman ini.', 'warning');
                return;
            }

            state.deletingId = Number(id);
            $('deletePermissionName').textContent = item.name;
            $('deletePermissionError').classList.add('d-none');
            $('deletePermissionError').textContent = '';
            $('btnConfirmDelete').disabled = false;
            $('btnConfirmDelete').textContent = 'Hapus';

            deleteModal.show();
        }

        async function confirmDelete() {
            if (!state.deletingId) return;

            const button = $('btnConfirmDelete');
            button.disabled = true;
            button.textContent = 'Menghapus...';

            try {
                await request(`${API_BASE}/delete/${state.deletingId}`, {
                    method: 'DELETE'
                });

                deleteModal.hide();
                notify('Permission berhasil dihapus.');
                state.deletingId = null;

                await loadPermissions();
            } catch (error) {
                const element = $('deletePermissionError');
                element.textContent = error.message;
                element.classList.remove('d-none');
            } finally {
                button.disabled = false;
                button.textContent = 'Hapus';
            }
        }

        $('btnCreate').addEventListener('click', openCreateModal);
        $('btnRefresh').addEventListener('click', loadPermissions);
        $('permissionForm').addEventListener('submit', savePermission);
        $('btnConfirmDelete').addEventListener('click', confirmDelete);

        $('btnSearch').addEventListener('click', () => {
            state.search = $('permissionSearch').value.trim();
            state.page = 1;
            loadPermissions();
        });

        $('permissionSearch').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                $('btnSearch').click();
            }
        });

        $('perPage').addEventListener('change', (event) => {
            state.perPage = Number(event.target.value) || 20;
            state.page = 1;
            loadPermissions();
        });

        $('permissionPagination').addEventListener('click', (event) => {
            const button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;

            const page = Number(button.dataset.page);
            if (page < 1 || page > state.totalPages || page === state.page) return;

            state.page = page;
            loadPermissions();
        });

        $('permissionTableBody').addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = Number(button.dataset.id);
            if (!Number.isInteger(id) || id < 1) return;

            if (button.dataset.action === 'edit') {
                openEditModal(id);
            } else if (button.dataset.action === 'delete') {
                openDeleteModal(id);
            }
        });
        const REGISTRY_API = <?= json_encode(
                                    base_url('api/v1/route-registry/registered'),
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                ) ?>;

        function registryNotice(message, type = 'danger') {
            const el = $('registryAlert');
            el.className = `alert alert-${type}`;
            el.textContent = message;
            el.classList.remove('d-none');
        }

        async function syncSelectedPermissions() {
            const candidates = window.registryPermissionCandidates ?? [];
            const selectedIndexes = [
                ...document.querySelectorAll('.js-permission-candidate:checked')
            ].map(input => Number(input.value));

            const selected = selectedIndexes
                .map(index => candidates[index])
                .filter(Boolean);

            if (!selected.length) {
                registryNotice('Pilih minimal satu permission.', 'warning');
                return;
            }

            const button = $('btnSyncPermissions');
            const notice = $('registrySyncNotice');

            button.disabled = true;
            button.textContent = 'Menyinkronkan...';

            let created = 0;
            let skipped = 0;
            const failures = [];

            try {
                for (const permission of selected) {
                    try {
                        await request(PERMISSION_CREATE_API, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                name: permission.name,
                                description: permission.description
                            })
                        });

                        created++;
                    } catch (error) {
                        if (
                            String(error.message).toLowerCase()
                            .includes('sudah digunakan')
                        ) {
                            skipped++;
                        } else {
                            failures.push({
                                name: permission.name,
                                message: error.message
                            });
                        }
                    }
                }

                notice.className = failures.length ?
                    'alert alert-warning' :
                    'alert alert-success';

                notice.textContent =
                    `Ditambahkan: ${created}; duplikat: ${skipped}; ` +
                    `gagal: ${failures.length}.` +
                    (failures.length ?
                        ' ' + failures.map(
                            item => `${item.name}: ${item.message}`
                        ).join(' | ') :
                        '');

                // Ambil ulang data aktual dari API.
                await loadRegistryComparison();
            } finally {
                button.disabled = false;
                button.textContent = 'Sinkronkan Terpilih';
            }
        }
        document.addEventListener('click', event => {
            if (!event.target.closest('#btnSyncPermissions')) return;

            event.preventDefault();
            syncSelectedPermissions();
        });
        async function loadRegistryComparison() {
            const button = $('btnLoadRegistry');
            const tbody = $('registryTableBody');

            button.disabled = true;
            tbody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-4">
                Memuat Route Registry...
            </td>
        </tr>`;

            try {
                const [registryResult, permissionResult] = await Promise.all([
                    request(REGISTRY_API),
                    request(API_BASE + '/getIndex')
                ]);

                const registryData = registryResult?.data.data ?? {};
                const permissionData = permissionResult?.data.data ?? {};

                const registryItems = Array.isArray(registryData.items) ?
                    registryData.items : [];

                const permissionItems = Array.isArray(permissionData.items) ?
                    permissionData.items : [];

                const existingNames = new Set(
                    permissionItems.map(item =>
                        String(item.name ?? '').trim().toLowerCase()
                    )
                );
                const permissionCandidates = new Map();

                registryItems.forEach(item => {
                    const registry = item.registry ?? {};

                    const names = Array.isArray(registry.permissions) ?
                        registry.permissions : [];

                    // Hanya sinkronkan mapping dari method yang aktif dan routable.
                    if (
                        Number(registry.is_routable ?? 0) !== 1 ||
                        Number(registry.is_allowed ?? 0) !== 1
                    ) {
                        return;
                    }

                    names.forEach(rawName => {
                        const name = String(rawName ?? '').trim();

                        if (!name) return;

                        const key = name.toLowerCase();

                        if (!permissionCandidates.has(key)) {
                            permissionCandidates.set(key, {
                                name,
                                description: `Permission ${name} dari Route Registry`
                            });
                        }
                    });
                });

                const missingCandidates = [...permissionCandidates.values()]
                    .filter(item => !existingNames.has(item.name.toLowerCase()));
                const rows = registryItems.map(item => {
                    const registry = item.registry ?? {};

                    // Registry dapat memiliki beberapa permission untuk satu method.
                    const names = Array.isArray(registry.permissions) ?
                        registry.permissions : [];

                    const mappedNames = [...new Set(
                        names
                        .map(name => String(name).trim())
                        .filter(Boolean)
                    )];

                    const matchedNames = mappedNames.filter(name =>
                        existingNames.has(name.toLowerCase())
                    );

                    const missingNames = mappedNames.filter(name =>
                        !existingNames.has(name.toLowerCase())
                    );

                    return {
                        module: item.module ?? registry.module ?? '—',
                        controller: item.controller ?? registry.controller ?? '—',
                        method: item.method ?? registry.method ?? '—',
                        endpoint: item.endpoint ?? registry.endpoint ?? '—',
                        urlRoute: registry.url_route ?? '—',
                        verbs: Array.isArray(registry.verbs) ?
                            registry.verbs : [],
                        routable: Number(registry.is_routable ?? 0) === 1,
                        allowed: Number(registry.is_allowed ?? 0) === 1,
                        names: mappedNames,
                        matchedNames,
                        missingNames
                    };
                });

                // Hitung permission unik, bukan jumlah method.
                const registryPermissionNames = new Set(
                    rows.flatMap(row => row.names.map(name => name.toLowerCase()))
                );

                const matchedUnique = [...registryPermissionNames]
                    .filter(name => existingNames.has(name)).length;

                const missingUnique = [...registryPermissionNames]
                    .filter(name => !existingNames.has(name)).length;

                $('registryTotal').textContent =
                    registryData.pagination?.total ?? rows.length;

                $('registryMatched').textContent = matchedUnique;
                $('registryMissing').textContent = missingUnique;

                if (!registryItems.length) {
                    console.error('Response Route Registry:', registryResult);

                    registryNotice(
                        'API berhasil dipanggil, tetapi data.items kosong atau struktur response berbeda.',
                        'warning'
                    );

                    tbody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center text-danger py-4">
                Tidak ada data Route Registry. Periksa response di Console.
            </td>
        </tr>`;
                    return;
                }

                tbody.innerHTML = rows.map(row => {
                    let status;

                    if (!row.routable || !row.allowed) {
                        status = `
                    <span class="badge bg-secondary">
                        Tidak aktif / tidak routable
                    </span>`;
                    } else if (row.names.length === 0) {
                        status = `
                    <span class="badge bg-warning text-dark">
                        Belum ada mapping permission
                    </span>`;
                    } else if (row.missingNames.length > 0) {
                        status = `
                    <span class="badge bg-warning text-dark">
                        Permission belum lengkap
                    </span>`;
                    } else {
                        status = `
                    <span class="badge bg-success">
                        Permission tersedia
                    </span>`;
                    }

                    const permissionHtml = row.names.length ?
                        row.names.map(name => {
                            const exists = existingNames.has(name.toLowerCase());

                            return `
                        <div>
                            <code>${escapeHtml(name)}</code>
                            <span class="badge ${exists ? 'bg-success' : 'bg-warning text-dark'}">
                                ${exists ? 'Ada' : 'Belum ada'}
                            </span>
                        </div>`;
                        }).join('') :
                        '<span class="text-muted">Belum dipetakan</span>';

                    return `
                <tr>
                    <td>
                        <div>${escapeHtml(row.module)}</div>
                        <small class="text-muted">
                            ${escapeHtml(row.verbs.join(', ') || 'Tanpa verb')}
                        </small>
                    </td>
                    <td>
                        <div>${escapeHtml(row.controller)}</div>
                        <small class="text-muted">
                            ${escapeHtml(row.method)}
                        </small>
                    </td>
                    <td>${permissionHtml}</td>
                    <td>${status}</td>
                </tr>`;
                }).join('');

                $('registryAlert').classList.add('d-none');

            } catch (error) {
                tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-danger py-4">
                    ${escapeHtml(error.message)}
                </td>
            </tr>`;

                registryNotice(error.message);
            } finally {
                button.disabled = false;
            }
        }

        function renderPermissionCandidates(candidates) {
            const container = $('registrySyncNotice');
            const syncButton = $('btnSyncPermissions');

            // Simpan daftar kandidat yang belum ada.
            window.registryPermissionCandidates = candidates;

            if (!candidates.length) {
                container.className = 'alert alert-success';
                container.textContent =
                    'Semua permission yang memiliki mapping aktif sudah tersedia.';
                syncButton.disabled = true;
                return;
            }

            container.className = 'alert alert-light border';
            container.innerHTML = `
        <div class="mb-2">
            Ditemukan <strong>${candidates.length}</strong>
            permission yang belum tersedia.
        </div>
        ${candidates.map((item, index) => `
            <label class="d-flex align-items-start gap-2 mb-2">
                <input type="checkbox"
                       class="form-check-input js-permission-candidate"
                       value="${index}">
                <span>
                    <code>${escapeHtml(item.name)}</code>
                    <small class="d-block text-muted">
                        Belum tersedia di Shield
                    </small>
                </span>
            </label>
        `).join('')}
    `;

            syncButton.disabled = true;
        }

        document.addEventListener('click', function(event) {
            const button = event.target.closest('#btnLoadRegistry');

            if (!button) {
                return;
            }

            event.preventDefault();
            loadRegistryComparison();
        });


        document.addEventListener('change', event => {
            if (!event.target.matches('.js-permission-candidate')) return;

            const selected = document.querySelectorAll(
                '.js-permission-candidate:checked'
            );

            $('btnSyncPermissions').disabled = selected.length === 0;
        });

        document.addEventListener('click', async event => {
            if (event.target.closest('#btnSelectMissing')) {
                document.querySelectorAll('.js-permission-candidate')
                    .forEach(input => {
                        input.checked = true;
                    });

                $('btnSyncPermissions').disabled =
                    window.registryPermissionCandidates.length === 0;
                return;
            }

            if (!event.target.closest('#btnSyncPermissions')) return;

            const button = $('btnSyncPermissions');
            const notice = $('registrySyncNotice');

            const candidates = window.registryPermissionCandidates ?? [];
            const selectedIndexes = [
                ...document.querySelectorAll('.js-permission-candidate:checked')
            ].map(input => Number(input.value));

            const selected = selectedIndexes
                .map(index => candidates[index])
                .filter(Boolean);

            if (!selected.length) return;

            if (!confirm(`Tambahkan ${selected.length} permission ke Shield?`)) {
                return;
            }

            button.disabled = true;
            let created = 0;
            let skipped = 0;
            const failures = [];

            try {
                for (const permission of selected) {
                    try {
                        await request(API_BASE + '/create', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                name: permission.name,
                                description: permission.description
                            })
                        });

                        created++;
                    } catch (error) {
                        if (String(error.message).includes('sudah digunakan')) {
                            skipped++;
                        } else {
                            failures.push(`${permission.name}: ${error.message}`);
                        }
                    }
                }

                notice.className = failures.length ?
                    'alert alert-warning' :
                    'alert alert-success';

                notice.textContent =
                    `Berhasil ditambahkan: ${created}. ` +
                    `Sudah ada/dilewati: ${skipped}. ` +
                    `Gagal: ${failures.length}.` +
                    (failures.length ? ` ${failures.join(' | ')}` : '');

                await loadRegistryComparison();
            } finally {
                button.disabled = false;
            }
        });

        loadPermissions();
    })();
</script>
<?= $this->endSection() ?>
