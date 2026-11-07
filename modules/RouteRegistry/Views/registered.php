<?php

declare(strict_types=1);

$verbs = $verbs ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
$seg   = $seg ?? 'route-registry';

$apiBase = rtrim(site_url('api/v1/route-registry'), '/');
?>

<div
    id="routeRegistryApp"
    data-api-base="<?= esc($apiBase, 'attr') ?>"
    data-initialized="false">
    <!-- Navigation -->
    <?= $this->include('Modules\RouteRegistry\Views\partials\navigation') ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Registered Endpoints</h4>
            <p class="text-muted mb-0">
                Kelola endpoint terdaftar, HTTP verbs, status akses, dan permission.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-outline-secondary" id="rrRefreshRegistered">
                <i class="ti ti-refresh me-1"></i> Refresh
            </button>
            <button type="button" id="btnSyncMenu"
                class="btn btn-outline-primary btn-sm">
                <i class="ti ti-list-check me-1"></i>
                Sync Menu
            </button>
            <a
                href="<?= esc(site_url('route-registry?view=scan'), 'attr') ?>"
                class="btn btn-primary">
                <i class="ti ti-scan me-1"></i> Scan Preview
            </a>
        </div>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4 d-none">
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Total Endpoints</div>
                    <h3 class="mb-0" id="rrTotalCount">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Routable</div>
                    <h3 class="mb-0" id="rrRoutableCount">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Allowed</div>
                    <h3 class="mb-0" id="rrAllowedCount">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Disabled</div>
                    <h3 class="mb-0" id="rrDisabledCount">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Registered endpoint table -->
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1">Endpoint Registry</h5>
                <p class="text-muted mb-0">Data endpoint yang sudah tersimpan di Route Registry.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <input
                    type="search"
                    class="form-control"
                    id="rrRegisteredSearch"
                    placeholder="Cari endpoint..."
                    aria-label="Cari registered endpoint"
                    style="min-width: 220px;">

                <!-- <button type="button" class="btn btn-outline-primary" id="rrManageVerbs">
                    <i class="ti ti-adjustments me-1"></i> HTTP Verbs
                </button> -->
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-sm mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;">No.</th>
                        <th style="min-width: 230px;">Endpoint</th>
                        <th style="min-width: 120px;">Module</th>
                        <th style="min-width: 200px;">Controller / Method</th>
                        <th class="text-center" style="width: 100px;">HTTP Verbs</th>
                        <th class="text-center">Routable</th>
                        <th class="text-center">Allowed</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="rrRegisteredBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Memuat registered endpoints...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted" id="rrRegisteredInfo">Belum ada data.</small>
            <select id="rrRegisteredPageSize"
                class="form-select form-select-sm"
                style="width: auto;">
                <option value="10">10</option>
                <option value="40">40</option>
                <option value="80">80</option>
                <option value="10000">10000</option>
            </select>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="rrRegisteredPrev">
                    Sebelumnya
                </button>
                <span id="rrRegisteredPage" class="small">1</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="rrRegisteredNext">
                    Berikutnya
                </button>
            </div>
        </div>
    </div>


</div>

<!-- HTTP verbs modal -->
<div
    class="modal fade"
    id="rrVerbsModal"
    tabindex="-1"
    aria-labelledby="rrVerbsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="rrVerbsModalLabel">HTTP Verbs</h5>
                    <p class="text-muted small mb-0" id="rrVerbsEndpoint"></p>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="rrVerbsEndpointId">

                <p class="text-muted small">
                    Pilih metode HTTP yang diizinkan untuk endpoint ini.
                </p>

                <div class="row g-2" id="rrVerbsOptions">
                    <?php foreach ($verbs as $verb): ?>
                        <div class="col-6">
                            <label class="border rounded p-2 d-flex align-items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="form-check-input rr-verb-option m-0"
                                    value="<?= esc(strtoupper((string) $verb), 'attr') ?>">
                                <span><?= esc(strtoupper((string) $verb)) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>

                <button type="button" class="btn btn-primary" id="rrSaveVerbs">
                    Simpan HTTP Verbs
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editEndpointModal" tabindex="-1"
    aria-labelledby="editEndpointModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEditEndpoint">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEndpointModalLabel">
                        Edit Identitas Endpoint
                    </h5>
                    <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="editEndpointId" name="id">

                    <div class="mb-3">
                        <label for="editEndpointModule" class="form-label">Module</label>
                        <input type="text" class="form-control"
                            id="editEndpointModule" name="module"
                            maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="editEndpointController" class="form-label">
                            Controller
                        </label>
                        <input type="text" class="form-control"
                            id="editEndpointController" name="controller"
                            maxlength="190" required>
                    </div>

                    <div class="mb-3">
                        <label for="editEndpointMethod" class="form-label">
                            Method
                        </label>
                        <input type="text" class="form-control"
                            id="editEndpointMethod" name="method"
                            maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Endpoint baru</label>
                        <input type="text" class="form-control"
                            id="editEndpointPreview" readonly>
                    </div>

                    <div class="alert alert-warning py-2 mb-0">
                        Perubahan identitas registry tidak otomatis mengganti
                        class atau method PHP maupun route CodeIgniter.
                    </div>
                    <div id="editEndpointError"
                        class="text-danger small mt-2 d-none"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"
                        id="btnSaveEndpointIdentity">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rrSyncMenuModal" tabindex="-1"
    aria-labelledby="rrSyncMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="rrSyncMenuModalLabel">
                        Sync Menu Registered
                    </h5>
                    <small class="text-muted">
                        Pilih endpoint, lalu sesuaikan title dan URL menu.
                    </small>
                </div>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                            id="rrMenuSelectAll">
                        <label class="form-check-label" for="rrMenuSelectAll">
                            Pilih semua
                        </label>
                    </div>
                    <span id="rrMenuCount" class="text-muted small">
                        0 endpoint
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width:45px">Pilih</th>
                                <th>Endpoint</th>
                                <th style="min-width:180px">Title Menu</th>
                                <th style="min-width:240px">URL Menu</th>
                                <th style="width:100px">Status</th>
                            </tr>
                        </thead>
                        <tbody id="rrSyncMenuBody">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    Klik Sync Menu untuk memuat data.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="button" id="btnSaveSyncMenu"
                    class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>
                    Simpan Menu
                </button>
            </div>
        </div>
    </div>
</div>

<?php $this->section('jsFooterInline') ?>
<script>
    (function() {
        'use strict';

        const app = document.getElementById('routeRegistryApp');

        if (!app || app.dataset.initialized === 'true') {
            return;
        }

        app.dataset.initialized = 'true';

        const apiBase = app.dataset.apiBase.replace(/\/+$/, '');
        const $ = (id) => document.getElementById(id);

        const state = {
            registered: [],
            filtered: [],
            page: 1,
            pageSize: 50,
            search: '',
            activeEndpoint: null,
            permissionItems: []
        };

        const endpoints = {
            registered: apiBase + '/registered',
            verbs: apiBase + '/getVerbs',
            saveVerbs: apiBase + '/saveVerbs',
            updateStatus: apiBase + '/updateStatus',
            previewPermissions: apiBase + '/previewShieldPermissions',
            syncPermissions: apiBase + '/syncShieldPermissions',
            menuPreview: apiBase + '/sync-menu-preview',
            menuSync: apiBase + '/sync-menus',
        };

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        function csrfHeaders() {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            const meta = document.querySelector('meta[name="csrf-token"]');

            if (meta && meta.content) {
                headers['X-CSRF-TOKEN'] = meta.content;
            }

            return headers;
        }

        async function request(url, options = {}) {
            const headers = {
                ...csrfHeaders(),
                ...(options.headers || {})
            };

            const config = {
                ...options,
                headers
            };

            if (config.body && typeof config.body !== 'string') {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(config.body);
            }

            const response = await fetch(url, config);
            const text = await response.text();

            let result = {};

            if (text) {
                try {
                    result = JSON.parse(text);
                } catch (error) {
                    throw new Error('Server tidak mengembalikan JSON yang valid.');
                }
            }

            const csrfToken = result.csrf_token;

            if (csrfToken) {
                const meta = document.querySelector('meta[name="csrf-token"]');

                if (meta) {
                    meta.content = csrfToken;
                }
            }

            if (!response.ok || result.status === false || result.error) {
                throw new Error(result.message || 'Permintaan gagal (' + response.status + ').');
            }

            return result;
        }

        function unwrap(result) {
            if (result && result.data !== undefined) {
                return result.data;
            }

            return result;
        }

        function extractList(result, keys = []) {
            if (Array.isArray(result?.data?.items)) {
                return result.data.items;
            }

            if (Array.isArray(result?.items)) {
                return result.items;
            }

            if (Array.isArray(result?.data)) {
                return result.data;
            }

            const data = result?.data;

            if (data && typeof data === 'object') {
                for (const key of keys) {
                    if (Array.isArray(data[key])) {
                        return data[key];
                    }
                }
            }

            return [];
        }

        function showMessage(message, type = 'info') {
            const existing = document.getElementById('rrFlashMessage');

            if (existing) {
                existing.remove();
            }

            const alert = document.createElement('div');
            alert.id = 'rrFlashMessage';
            alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
            alert.setAttribute('role', 'alert');

            alert.innerHTML =
                '<span>' + escapeHtml(message) + '</span>' +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>';

            app.prepend(alert);

            alert.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function setBusy(button, busy, busyText = 'Memproses...') {
            if (!button) {
                return;
            }

            if (busy) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' +
                    escapeHtml(busyText);
            } else {
                button.disabled = false;

                if (button.dataset.originalHtml !== undefined) {
                    button.innerHTML = button.dataset.originalHtml;
                    delete button.dataset.originalHtml;
                }
            }
        }

        function endpointId(item) {
            return item.id ?? item.registry_id ?? item.route_registry_id ?? '';
        }

        function endpointName(item) {
            return item.endpoint ??
                item.route ??
                item.uri ??
                item.path ??
                '';
        }

        function moduleName(item) {
            return item.module_name ??
                item.module ??
                item.registry_module ??
                '';
        }

        function controllerMethod(item) {
            const controller = item.controller ?? item.controller_name ?? '';
            const method = item.method ?? item.action ?? '';

            if (controller && method) {
                return controller + '::' + method;
            }

            return item.handler ?? item.class_name ?? controller ?? method ?? '—';
        }

        function isEnabled(value) {
            return value === true ||
                value === 1 ||
                value === 'true';
        }

        function statusBadge(enabled, yes = 'Aktif', no = 'Nonaktif') {
            return enabled ?
                '<span class="badge bg-success-subtle text-success">' + yes + '</span>' :
                '<span class="badge bg-secondary-subtle text-secondary">' + no + '</span>';
        }

        function renderRegistered() {
            const body = $('rrRegisteredBody');
            const start = (state.page - 1) * state.pageSize;
            const pageItems = state.filtered.slice(start, start + state.pageSize);

            if (!pageItems.length) {
                body.innerHTML =
                    '<tr><td colspan="7" class="text-center py-4 text-muted">' +
                    (state.search ?
                        'Tidak ada endpoint yang cocok dengan pencarian.' :
                        'Belum ada registered endpoint.') +
                    '</td></tr>';

                updatePagination();
                updateCounters();
                return;
            }

            body.innerHTML = pageItems.map((item, index) => {
                const registry = item.registry ?? {};

                const id = registry.id ?? '';
                const number = ((state.page - 1) * state.pageSize) + index + 1;

                const routable = isEnabled(registry.is_routable);
                const allowed = isEnabled(registry.is_allowed);

                const verbs = Array.isArray(registry.verbs) ?
                    registry.verbs : [];

                const permissions = Array.isArray(registry.permissions) ?
                    registry.permissions.map(v => String(v)) : [];

                return `
                <tr data-id="${escapeHtml(id)}">
                    <td class="text-center">${number}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(endpointName(item) || '—')}</div>

                        <small class="text-muted"><code>${escapeHtml(controllerMethod(item))}</code></small>
                    </td>
                    <td>${escapeHtml(moduleName(item) || '—')}</td>
                    <td> ${permissions.length
                                    ? permissions.map(v => v).join('')
                                    : '-'}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            ${['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(verb => `
                                <label class="d-inline-flex align-items-center gap-1 mb-0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input mt-0 http-verb-checkbox"
                                        data-endpoint="${escapeHtml(registry.endpoint ?? item.endpoint ?? '')}"
                                        value="${verb}"
                                        ${verbs.includes(verb) ? 'checked' : ''}
                                    >
                                    <span class="small">${verb}</span>
                                </label>
                            `).join('')}
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-flex justify-content-center">
                            <input
                                type="checkbox"
                                class="form-check-input rr-status-toggle"
                                data-id="${escapeHtml(id)}"
                                data-field="is_routable"
                                aria-label="Ubah status routable"
                                ${routable ? 'checked' : ''}
                            >
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-flex justify-content-center">
                            <input
                                type="checkbox"
                                class="form-check-input rr-status-toggle"
                                data-id="${escapeHtml(id)}"
                                data-field="is_allowed"
                                aria-label="Ubah status allowed"
                                ${allowed ? 'checked' : ''}
                            >
                        </div>
                    </td>
                    <td>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary btn-edit-endpoint"
                            data-id="${escapeHtml(id)}"
                            data-module="${escapeHtml(item.registry?.module ?? item.module ?? '')}"
                            data-controller="${escapeHtml(item.registry?.controller ?? item.controller ?? '')}"
                            data-method="${escapeHtml(item.registry?.method ?? item.method ?? '')}"
                            title="Edit identitas endpoint"
                        >
                            <i class="ti ti-edit"></i>
                            Edit
                        </button>
                    </td>

                </tr>
            `;
            }).join('');

            updatePagination();
            updateCounters();
        }

        function updatePagination() {
            const total = state.filtered.length;
            const pages = Math.max(1, Math.ceil(total / state.pageSize));

            if (state.page > pages) {
                state.page = pages;
            }

            const start = total > 0 ?
                ((state.page - 1) * state.pageSize) + 1 :
                0;

            const end = Math.min(state.page * state.pageSize, total);

            $('rrRegisteredPage').textContent =
                state.page + ' / ' + pages;

            $('rrRegisteredInfo').textContent = total ?
                `Menampilkan ${start}–${end} dari ${total} endpoint` :
                'Tidak ada data.';

            $('rrRegisteredPrev').disabled = state.page <= 1;
            $('rrRegisteredNext').disabled = state.page >= pages;

            const select = $('rrRegisteredPageSize');

            if (select) {
                select.value = String(state.pageSize);
            }
        }

        function updateCounters() {
            const items = state.registered;

            $('rrTotalCount').textContent = items.length;

            $('rrRoutableCount').textContent = items.filter(item =>
                isEnabled((item.registry ?? {}).is_routable)
            ).length;

            $('rrAllowedCount').textContent = items.filter(item =>
                isEnabled((item.registry ?? {}).is_allowed)
            ).length;

            $('rrDisabledCount').textContent = items.filter(item =>
                !isEnabled((item.registry ?? {}).is_routable) ||
                !isEnabled((item.registry ?? {}).is_allowed)
            ).length;
        }

        function filterRegistered() {
            const keyword = state.search.trim().toLowerCase();

            state.filtered = !keyword ? [...state.registered] :
                state.registered.filter((item) => [
                    endpointName(item),
                    moduleName(item),
                    controllerMethod(item),
                    item.permission,
                    item.permission_name
                ].some((value) =>
                    String(value ?? '').toLowerCase().includes(keyword)
                ));

            state.page = 1;
            renderRegistered();
        }

        async function loadRegistered(button = null) {
            setBusy(button, true, 'Memuat...');

            $('rrRegisteredBody').innerHTML =
                '<tr><td colspan="7" class="text-center py-4">' +
                '<span class="spinner-border spinner-border-sm me-2"></span>Memuat data...' +
                '</td></tr>';

            try {
                // Pulihkan pencarian yang tersimpan.
                const savedSearch = localStorage.getItem('rr_registered_endpoint_search');

                if (savedSearch !== null) {
                    state.search = savedSearch;
                    $('rrRegisteredSearch').value = savedSearch;
                }

                const result = await request(endpoints.registered, {
                    method: 'GET'
                });

                state.registered = extractList(result, [
                    'items',
                    'registered',
                    'routes',
                    'endpoints'
                ]);

                filterRegistered();
            } catch (error) {
                $('rrRegisteredBody').innerHTML =
                    '<tr><td colspan="7" class="text-center py-4 text-danger">' +
                    escapeHtml(error.message) +
                    '</td></tr>';

                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        async function loadRegistered2(button = null) {
            setBusy(button, true, 'Memuat...');

            $('rrRegisteredBody').innerHTML =
                '<tr><td colspan="7" class="text-center py-4">' +
                '<span class="spinner-border spinner-border-sm me-2"></span>Memuat data...' +
                '</td></tr>';

            try {
                const result = await request(endpoints.registered, {
                    method: 'GET'
                });

                state.registered = extractList(result, [
                    'items',
                    'registered',
                    'routes',
                    'endpoints'
                ]);

                filterRegistered();
            } catch (error) {
                $('rrRegisteredBody').innerHTML =
                    '<tr><td colspan="7" class="text-center py-4 text-danger">' +
                    escapeHtml(error.message) +
                    '</td></tr>';

                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        async function updateStatus(input) {
            const id = Number(input.dataset.id);

            const fieldMap = {
                is_routable: 'routable',
                is_allowed: 'allowed'
            };

            const field = fieldMap[input.dataset.field];
            const value = input.checked;
            const previousValue = !value;

            if (
                !Number.isInteger(id) ||
                id < 1 ||
                !field ||
                typeof value !== 'boolean'
            ) {
                input.checked = previousValue;
                showAlert(
                    'ID, jenis status, atau nilai status tidak valid.',
                    'danger'
                );
                return;
            }

            const scanIndex = input.dataset.index;
            const isScanToggle = input.classList.contains(
                'rr-scan-status-toggle'
            );

            input.disabled = true;

            try {
                await request(endpoints.updateStatus, {
                    method: 'PATCH',
                    body: {
                        id: id,
                        field: field,
                        value: value
                    }
                });

                const item = state.registered.find(row =>
                    String((row.registry ?? {}).id) === String(id)
                );

                if (item) {
                    const registry = item.registry ?? (item.registry = {});

                    registry[
                        field === 'routable' ? 'is_routable' : 'is_allowed'
                    ] = value;
                }

                updateCounters();

                showAlert('Status endpoint berhasil diperbarui.', 'success');
            } catch (error) {
                input.checked = previousValue;
                showAlert(error.message, 'danger');
            } finally {
                input.disabled = false;
            }
        }

        async function openVerbsModal(id, endpoint) {
            state.activeEndpoint = id;

            $('rrVerbsEndpointId').value = id;
            $('rrVerbsEndpoint').textContent = endpoint || ('Endpoint ID: ' + id);

            document.querySelectorAll('.rr-verb-option').forEach((checkbox) => {
                checkbox.checked = false;
            });

            try {
                const result = await request(
                    endpoints.verbs + '/' + encodeURIComponent(id), {
                        method: 'GET'
                    }
                );

                const data = unwrap(result);
                const verbs = Array.isArray(data) ?
                    data :
                    (data.verbs ?? data.http_verbs ?? data.allowed_verbs ?? []);

                document.querySelectorAll('.rr-verb-option').forEach((checkbox) => {
                    checkbox.checked = verbs.some((verb) =>
                        String(typeof verb === 'object' ? verb.verb ?? verb.method : verb)
                        .toUpperCase() === checkbox.value
                    );
                });
            } catch (error) {
                showAlert('Gagal memuat HTTP verbs: ' + error.message, 'danger');
                return;
            }

            const modalElement = $('rrVerbsModal');

            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            } else {
                showAlert('Bootstrap Modal tidak tersedia pada layout.', 'danger');
            }
        }

        async function saveVerbs(button) {
            const id = $('rrVerbsEndpointId').value;

            const verbs = Array.from(
                document.querySelectorAll('.rr-verb-option:checked')
            ).map((checkbox) => checkbox.value);

            if (!id) {
                showAlert('Pilih endpoint terlebih dahulu.', 'warning');
                return;
            }

            setBusy(button, true, 'Menyimpan...');

            try {
                await request(endpoints.saveVerbs, {
                    method: 'POST',
                    body: {
                        id: Number(id),
                        verbs
                    }
                });

                showAlert('HTTP verbs berhasil disimpan.', 'success');

                const modalElement = $('rrVerbsModal');

                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                }

                await loadRegistered();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }


        let syncMenuRows = [];

        function renderSyncMenuRows() {
            const tbody = $('rrSyncMenuBody');

            $('rrMenuCount').textContent =
                `${syncMenuRows.length} endpoint`;

            if (syncMenuRows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="5" class="text-center py-4">' +
                    'Tidak ada endpoint dengan url_route yang valid.' +
                    '</td></tr>';

                $('rrMenuSelectAll').checked = false;
                return;
            }

            tbody.innerHTML = syncMenuRows.map((item, index) => `
        <tr>
            <td class="text-center">
                <input type="checkbox"
                    class="form-check-input rr-menu-selected"
                    data-index="${index}">
            </td>
            <td>
                <div class="fw-semibold">
                    ${escapeHtml(item.endpoint || '')}
                </div>
                <small class="text-muted">
                    ${escapeHtml(item.module || '')}
                    · ID ${escapeHtml(item.route_id)}
                </small>
            </td>
            <td>
                <input type="text"
                    class="form-control form-control-sm rr-menu-title"
                    data-index="${index}"
                    maxlength="100"
                    value="${escapeHtml(item.title || '')}">
            </td>
            <td>
                <input type="text"
                    class="form-control form-control-sm rr-menu-url"
                    data-index="${index}"
                    maxlength="255"
                    value="${escapeHtml(item.url || '')}">
            </td>
            <td>
                ${item.is_synced
                    ? '<span class="badge bg-success">Terdaftar</span>'
                    : '<span class="badge bg-secondary">Baru</span>'}
            </td>
        </tr>
    `).join('');

            $('rrMenuSelectAll').checked = false;
        }

        async function openSyncMenuModal(button = null) {
            setBusy(button, true, 'Memuat...');

            $('rrSyncMenuBody').innerHTML =
                '<tr><td colspan="5" class="text-center py-4">' +
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Memuat endpoint...' +
                '</td></tr>';

            bootstrap.Modal.getOrCreateInstance(
                $('rrSyncMenuModal')
            ).show();

            try {
                const result = await request(endpoints.menuPreview, {
                    method: 'GET'
                });

                if (result.status === false) {
                    throw new Error(result.message || 'Gagal memuat menu.');
                }

                syncMenuRows = result.data?.items ??
                    result.items ?? [];

                renderSyncMenuRows();
            } catch (error) {
                $('rrSyncMenuBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger py-4">' +
                    escapeHtml(error.message) +
                    '</td></tr>';

                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        $('btnSyncMenu').addEventListener('click', function() {
            openSyncMenuModal(this);
        });

        $('rrMenuSelectAll').addEventListener('change', function() {
            document.querySelectorAll('.rr-menu-selected').forEach(input => {
                input.checked = this.checked;
            });
        });

        $('btnSaveSyncMenu').addEventListener('click', async function() {
            const button = this;
            const selected = [];

            document.querySelectorAll('.rr-menu-selected:checked')
                .forEach(input => {
                    const index = Number(input.dataset.index);
                    const item = syncMenuRows[index];

                    const titleInput = document.querySelector(
                        `.rr-menu-title[data-index="${index}"]`
                    );

                    const urlInput = document.querySelector(
                        `.rr-menu-url[data-index="${index}"]`
                    );

                    const title = titleInput.value.trim();
                    const url = urlInput.value.trim();

                    if (!title || title.length > 100) {
                        throw new Error(
                            `Title menu endpoint ${item.endpoint} wajib diisi, maksimal 100 karakter.`
                        );
                    }

                    if (!url || url.length > 255) {
                        throw new Error(
                            `URL menu endpoint ${item.endpoint} wajib diisi, maksimal 255 karakter.`
                        );
                    }

                    selected.push({
                        route_id: Number(item.route_id),
                        title,
                        url
                    });
                });

            if (selected.length === 0) {
                showAlert('Pilih minimal satu endpoint.', 'warning');
                return;
            }

            try {
                setBusy(button, true, 'Menyimpan...');

                const result = await request(endpoints.menuSync, {
                    method: 'POST',
                    body: JSON.stringify({
                        items: selected
                    })
                });

                if (result.status === false) {
                    throw new Error(result.message || 'Gagal menyimpan menu.');
                }

                const data = result.data || {};

                bootstrap.Modal.getOrCreateInstance(
                    $('rrSyncMenuModal')
                ).hide();

                showAlert(
                    `${result.message} Ditambahkan: ${data.inserted ?? 0}, ` +
                    `diperbarui: ${data.updated ?? 0}.`,
                    'success'
                );

                // Data Registered tetap memakai API dan filter yang sudah ada.
                await loadRegistered();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        });


        $('btnSaveSyncMenu').addEventListener('click', async function() {
            const button = this;

            try {
                const selected = [];

                document.querySelectorAll('.rr-menu-selected:checked')
                    .forEach(input => {
                        const index = Number(input.dataset.index);
                        const item = syncMenuRows[index];

                        const title = document.querySelector(
                            `.rr-menu-title[data-index="${index}"]`
                        ).value.trim();

                        const url = document.querySelector(
                            `.rr-menu-url[data-index="${index}"]`
                        ).value.trim();

                        if (!title || title.length > 100) {
                            throw new Error(
                                `Title endpoint ${item.endpoint} wajib diisi, maksimal 100 karakter.`
                            );
                        }

                        if (!url || url.length > 255) {
                            throw new Error(
                                `URL endpoint ${item.endpoint} wajib diisi, maksimal 255 karakter.`
                            );
                        }

                        selected.push({
                            route_id: Number(item.route_id),
                            title,
                            url
                        });
                    });

                if (selected.length === 0) {
                    showAlert('Pilih minimal satu endpoint.', 'warning');
                    return;
                }

                setBusy(button, true, 'Menyimpan...');

                const result = await request(endpoints.menuSync, {
                    method: 'POST',
                    body: JSON.stringify({
                        items: selected
                    })
                });

                if (result.status === false) {
                    throw new Error(result.message || 'Gagal menyimpan menu.');
                }

                const data = result.data || {};

                bootstrap.Modal.getOrCreateInstance(
                    $('rrSyncMenuModal')
                ).hide();

                showAlert(
                    `${result.message} Ditambahkan: ${data.inserted ?? 0}, ` +
                    `diperbarui: ${data.updated ?? 0}.`,
                    'success'
                );

                await loadRegistered();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        });



        $('rrRefreshRegistered').addEventListener('click', function() {
            loadRegistered(this);
        });

        /* $('rrRegisteredSearch').addEventListener('input', function() {
            state.search = this.value;
            filterRegistered();
        }); */

        $('rrRegisteredSearch').addEventListener('input', function() {
            state.search = this.value;

            localStorage.setItem(
                'rr_registered_endpoint_search',
                state.search
            );

            filterRegistered();
        });

        $('rrRegisteredPrev').addEventListener('click', function() {
            if (state.page > 1) {
                state.page--;
                renderRegistered();
            }
        });

        $('rrRegisteredNext').addEventListener('click', function() {
            const pages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));

            if (state.page < pages) {
                state.page++;
                renderRegistered();
            }
        });

        $('rrRegisteredPageSize').addEventListener('change', function() {
            const allowedSizes = [10, 40, 80, 10000];
            const selectedSize = Number(this.value);

            if (!allowedSizes.includes(selectedSize)) {
                this.value = String(state.pageSize);
                return;
            }

            state.pageSize = selectedSize;
            state.page = 1;

            renderRegistered();
        });


        $('rrRegisteredBody').addEventListener('click', function(event) {
            const button = event.target.closest('.rr-edit-verbs');

            if (!button) {
                return;
            }

            openVerbsModal(button.dataset.id, button.dataset.endpoint);
        });

        $('rrRegisteredBody').addEventListener('change', function(event) {
            const input = event.target.closest('.rr-status-toggle');

            if (input) {
                updateStatus(input);
            }
        });

        $('rrSaveVerbs').addEventListener('click', function() {
            saveVerbs(this);
        });

        document.addEventListener('change', async function(event) {
            const checkbox = event.target.closest('.http-verb-checkbox');

            if (!checkbox) {
                return;
            }
            const endpoint = checkbox.dataset.endpoint;

            if (!endpoint) {
                checkbox.checked = !checkbox.checked;
                showAlert('Endpoint tidak ditemukan.', 'danger');
                return;
            }

            const row = checkbox.closest('tr');
            const id = row?.dataset.id;

            if (!id) {
                checkbox.checked = !checkbox.checked;
                showAlert('ID endpoint tidak ditemukan.', 'danger');
                return;
            }

            const checkboxes = Array.from(
                row.querySelectorAll('.http-verb-checkbox')
            );

            // Kembalikan kondisi checkbox sebelum perubahan.
            const previousChecked = !checkbox.checked;

            // Bentuk daftar verb berdasarkan pilihan saat ini.
            const verbs = checkboxes
                .filter(input => input.checked)
                .map(input => input.value);

            checkboxes.forEach(input => {
                input.disabled = true;
            });

            try {
                await request(endpoints.saveVerbs, {
                    method: 'POST',
                    body: {
                        endpoint,
                        verbs
                    }
                });

                const item = state.registered.find(rowItem =>
                    String((rowItem.registry ?? {}).id) === String(id)
                );

                if (item) {
                    item.registry.verbs = [...verbs];
                }

                showAlert('HTTP verbs berhasil disimpan.', 'success');
            } catch (error) {
                checkbox.checked = previousChecked;

                showAlert(
                    error.message || 'Gagal menyimpan HTTP verbs.',
                    'danger'
                );
            } finally {
                checkboxes.forEach(input => {
                    input.disabled = false;
                });
            }
        });

        loadRegistered();
    })();

    (() => {
        const modalElement = document.getElementById('editEndpointModal');
        const form = document.getElementById('formEditEndpoint');

        if (!modalElement || !form || form.dataset.initialized === '1') {
            return;
        }
        form.dataset.initialized = '1';

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const idInput = document.getElementById('editEndpointId');
        const moduleInput = document.getElementById('editEndpointModule');
        const controllerInput = document.getElementById('editEndpointController');
        const methodInput = document.getElementById('editEndpointMethod');
        const preview = document.getElementById('editEndpointPreview');
        const errorBox = document.getElementById('editEndpointError');
        const saveButton = document.getElementById('btnSaveEndpointIdentity');

        const API_URL = '<?= rtrim(site_url("api/v1/route-registry"), "/") ?>/update-endpoint-identity';

        function updatePreview() {
            preview.value =
                `${controllerInput.value.trim()}::${methodInput.value.trim()}`;
        }

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
        }

        function clearError() {
            errorBox.textContent = '';
            errorBox.classList.add('d-none');
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-edit-endpoint');
            if (!button) return;

            idInput.value = button.dataset.id || '';
            moduleInput.value = button.dataset.module || '';
            controllerInput.value = button.dataset.controller || '';
            methodInput.value = button.dataset.method || '';

            clearError();
            updatePreview();
            modal.show();
        });

        [controllerInput, methodInput].forEach((input) => {
            input.addEventListener('input', updatePreview);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearError();

            const payload = {
                id: Number(idInput.value),
                module: moduleInput.value.trim(),
                controller: controllerInput.value.trim(),
                method: methodInput.value.trim()
            };

            if (!payload.id || !payload.module ||
                !payload.controller || !payload.method) {
                showError('Semua kolom wajib diisi.');
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = 'Menyimpan...';

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (!response.ok || result.status !== true) {
                    throw new Error(
                        result.message || 'Gagal memperbarui endpoint.'
                    );
                }

                // Jangan menutup modal sebelum server memastikan berhasil.
                modal.hide();

                // Beri sinyal agar tabel dapat diperbarui oleh fungsi pemuat
                // yang sudah digunakan halaman ini.
                document.dispatchEvent(new CustomEvent(
                    'route-registry:identity-updated', {
                        detail: result.data
                    }
                ));

                if (typeof window.loadRegisteredEndpoints === 'function') {
                    await window.loadRegisteredEndpoints();
                } else {
                    window.location.reload();
                }
            } catch (error) {
                showError(error.message || 'Terjadi kesalahan saat menyimpan.');
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'Simpan Perubahan';
            }
        });
    })();
</script>
<?php $this->endSection() ?>
