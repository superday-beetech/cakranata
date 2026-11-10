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

            <a
                href="<?= esc(site_url('route-registry?view=scan'), 'attr') ?>"
                class="btn btn-primary">
                <i class="ti ti-scan me-1"></i> Scan Preview
            </a>
        </div>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
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

                <button type="button" class="btn btn-outline-primary" id="rrManageVerbs">
                    <i class="ti ti-adjustments me-1"></i> HTTP Verbs
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 230px;">Endpoint</th>
                        <th style="min-width: 180px;">Module</th>
                        <th style="min-width: 200px;">Controller / Method</th>
                        <th class="text-center">Routable</th>
                        <th class="text-center">Allowed</th>
                        <th style="min-width: 130px;">HTTP Verbs</th>
                        <th class="text-end">Actions</th>
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

    <!-- Permission comparison -->
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1">Shield Permission Registry</h5>
                <p class="text-muted mb-0">
                    Periksa permission dari Route Registry sebelum melakukan sinkronisasi ke Shield.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary" id="rrLoadPermissionComparison">
                    <i class="ti ti-refresh me-1"></i> Muat Perbandingan
                </button>

                <button type="button" class="btn btn-primary" id="rrSyncShieldPermissions">
                    <i class="ti ti-shield-check me-1"></i> Sinkronisasi Shield
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info" role="alert">
                Sinkronisasi hanya dilakukan setelah dikonfirmasi. Data Route Registry tetap menjadi
                sumber inventaris endpoint; permission Shield yang tidak lagi ditemukan saat scan
                tidak otomatis dihapus.
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Registry Permissions</div>
                        <h4 class="mb-0" id="rrPermissionRegistryCount">0</h4>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Shield Permissions</div>
                        <h4 class="mb-0" id="rrPermissionShieldCount">0</h4>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Belum Ada di Shield</div>
                        <h4 class="mb-0" id="rrPermissionMissingCount">0</h4>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Perlu Ditinjau</div>
                        <h4 class="mb-0" id="rrPermissionReviewCount">0</h4>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 180px;">Module</th>
                            <th style="min-width: 240px;">Controller / Method</th>
                            <th style="min-width: 240px;">Registry Permission</th>
                            <th style="min-width: 160px;">Status Shield</th>
                        </tr>
                    </thead>

                    <tbody id="rrPermissionComparisonBody">
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                Klik “Muat Perbandingan” untuk memeriksa permission.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
            pageSize: 10,
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
            syncPermissions: apiBase + '/syncShieldPermissions'
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
            const data = unwrap(result);

            if (Array.isArray(data)) {
                return data;
            }

            if (data && typeof data === 'object') {
                for (const key of keys) {
                    if (Array.isArray(data[key])) {
                        return data[key];
                    }
                }

                if (data.items && Array.isArray(data.items)) {
                    return data.items;
                }

                if (data.results && Array.isArray(data.results)) {
                    return data.results;
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
                value === '1' ||
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

            body.innerHTML = pageItems.map((item) => {
                const id = endpointId(item);
                const routable = isEnabled(item.is_routable);
                const allowed = isEnabled(item.is_allowed);
                const verbs = Array.isArray(item.verbs) ?
                    item.verbs :
                    (Array.isArray(item.http_verbs) ? item.http_verbs : []);

                return `
                <tr data-id="${escapeHtml(id)}">
                    <td>
                        <div class="fw-semibold">${escapeHtml(endpointName(item) || '—')}</div>
                        <small class="text-muted">ID: ${escapeHtml(id || '—')}</small>
                    </td>
                    <td>${escapeHtml(moduleName(item) || '—')}</td>
                    <td><code>${escapeHtml(controllerMethod(item))}</code></td>
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
                        <div class="d-flex flex-wrap gap-1">
                            ${verbs.length
                                ? verbs.map((verb) =>
                                    '<span class="badge bg-light text-dark border">' +
                                    escapeHtml(verb) + '</span>'
                                ).join('')
                                : '<span class="text-muted small">Belum diatur</span>'}
                        </div>
                    </td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary rr-edit-verbs"
                            data-id="${escapeHtml(id)}"
                            data-endpoint="${escapeHtml(endpointName(item))}"
                        >
                            <i class="ti ti-adjustments"></i> Verbs
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

            $('rrRegisteredPage').textContent = state.page + ' / ' + pages;
            $('rrRegisteredInfo').textContent = total ?
                'Menampilkan ' + (Math.min((state.page - 1) * state.pageSize + 1, total)) +
                '–' + Math.min(state.page * state.pageSize, total) +
                ' dari ' + total + ' endpoint' :
                'Tidak ada data.';

            $('rrRegisteredPrev').disabled = state.page <= 1;
            $('rrRegisteredNext').disabled = state.page >= pages;
        }

        function updateCounters() {
            const items = state.registered;

            $('rrTotalCount').textContent = items.length;
            $('rrRoutableCount').textContent =
                items.filter((item) => isEnabled(item.is_routable)).length;
            $('rrAllowedCount').textContent =
                items.filter((item) => isEnabled(item.is_allowed)).length;
            $('rrDisabledCount').textContent =
                items.filter((item) =>
                    !isEnabled(item.is_routable) || !isEnabled(item.is_allowed)
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

                showMessage(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        async function updateStatus(input) {
            const id = input.dataset.id;
            const field = input.dataset.field;
            const value = input.checked ? 1 : 0;

            if (!id || !['is_routable', 'is_allowed'].includes(field)) {
                showMessage('Data status endpoint tidak valid.', 'danger');
                return;
            }

            input.disabled = true;

            try {
                await request(endpoints.updateStatus, {
                    method: 'PATCH',
                    body: {
                        id,
                        field,
                        value
                    }
                });

                const item = state.registered.find((row) =>
                    String(endpointId(row)) === String(id)
                );

                if (item) {
                    item[field] = value;
                }

                updateCounters();
                showMessage('Status endpoint berhasil diperbarui.', 'success');
            } catch (error) {
                input.checked = !input.checked;
                showMessage(error.message, 'danger');
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
                showMessage('Gagal memuat HTTP verbs: ' + error.message, 'danger');
                return;
            }

            const modalElement = $('rrVerbsModal');

            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            } else {
                showMessage('Bootstrap Modal tidak tersedia pada layout.', 'danger');
            }
        }

        async function saveVerbs(button) {
            const id = $('rrVerbsEndpointId').value;

            const verbs = Array.from(
                document.querySelectorAll('.rr-verb-option:checked')
            ).map((checkbox) => checkbox.value);

            if (!id) {
                showMessage('Pilih endpoint terlebih dahulu.', 'warning');
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

                showMessage('HTTP verbs berhasil disimpan.', 'success');

                const modalElement = $('rrVerbsModal');

                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                }

                await loadRegistered();
            } catch (error) {
                showMessage(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        function permissionRows(result) {
            const data = unwrap(result);

            if (Array.isArray(data)) {
                return data;
            }

            return extractList(data, [
                'items',
                'permissions',
                'comparison',
                'registry_permissions'
            ]);
        }

        function renderPermissionComparison(items) {
            state.permissionItems = items;

            const body = $('rrPermissionComparisonBody');

            if (!items.length) {
                body.innerHTML =
                    '<tr><td colspan="4" class="text-center py-4 text-muted">' +
                    'Tidak ada data perbandingan permission atau format respons API belum sesuai.' +
                    '</td></tr>';

                $('rrPermissionRegistryCount').textContent = '0';
                $('rrPermissionShieldCount').textContent = '0';
                $('rrPermissionMissingCount').textContent = '0';
                $('rrPermissionReviewCount').textContent = '0';
                return;
            }

            const missing = items.filter((item) =>
                item.in_shield === false ||
                item.exists_in_shield === false ||
                item.shield_exists === false ||
                item.status === 'missing'
            ).length;

            const review = items.filter((item) =>
                item.needs_review === true ||
                item.status === 'review' ||
                item.status === 'orphaned'
            ).length;

            $('rrPermissionRegistryCount').textContent = items.length;
            $('rrPermissionShieldCount').textContent =
                items.filter((item) =>
                    item.in_shield === true ||
                    item.exists_in_shield === true ||
                    item.shield_exists === true
                ).length;
            $('rrPermissionMissingCount').textContent = missing;
            $('rrPermissionReviewCount').textContent = review;

            body.innerHTML = items.map((item) => {
                const module = item.module_name ?? item.module ?? '—';
                const handler = item.handler ??
                    item.controller_method ??
                    item.controller ??
                    item.method ??
                    '—';

                const permission = item.permission ??
                    item.permission_name ??
                    item.registry_permission ??
                    '—';

                const inShield = item.in_shield === true ||
                    item.exists_in_shield === true ||
                    item.shield_exists === true;

                let status = inShield ?
                    statusBadge(true, 'Ada di Shield') :
                    statusBadge(false, 'Belum di Shield');

                if (item.needs_review === true || item.status === 'review') {
                    status = '<span class="badge bg-warning-subtle text-warning">Perlu ditinjau</span>';
                }

                return `
                <tr>
                    <td>${escapeHtml(module)}</td>
                    <td><code>${escapeHtml(handler)}</code></td>
                    <td><code>${escapeHtml(permission)}</code></td>
                    <td>${status}</td>
                </tr>
            `;
            }).join('');
        }

        async function loadPermissionComparison(button = null) {
            setBusy(button, true, 'Memuat...');

            $('rrPermissionComparisonBody').innerHTML =
                '<tr><td colspan="4" class="text-center py-4">' +
                '<span class="spinner-border spinner-border-sm me-2"></span>Memuat perbandingan...' +
                '</td></tr>';

            try {
                const result = await request(endpoints.previewPermissions, {
                    method: 'GET'
                });

                renderPermissionComparison(permissionRows(result));
            } catch (error) {
                $('rrPermissionComparisonBody').innerHTML =
                    '<tr><td colspan="4" class="text-center py-4 text-danger">' +
                    escapeHtml(error.message) +
                    '</td></tr>';

                showMessage(
                    'Gagal memuat perbandingan permission. Pastikan endpoint preview Shield tersedia. ' +
                    error.message,
                    'danger'
                );
            } finally {
                setBusy(button, false);
            }
        }

        async function syncShieldPermissions(button) {
            const confirmed = window.confirm(
                'Lanjutkan sinkronisasi permission dari Route Registry ke Shield?\n\n' +
                'Tindakan ini akan mengubah data permission Shield. Periksa hasil preview terlebih dahulu.'
            );

            if (!confirmed) {
                return;
            }

            setBusy(button, true, 'Sinkronisasi...');

            try {
                const result = await request(endpoints.syncPermissions, {
                    method: 'POST',
                    body: {}
                });

                const data = unwrap(result);
                const message = data.message ??
                    result.message ??
                    'Permintaan sinkronisasi berhasil diproses.';

                showMessage(message, 'success');
                await loadPermissionComparison();
            } catch (error) {
                showMessage('Sinkronisasi gagal: ' + error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        $('rrRefreshRegistered').addEventListener('click', function() {
            loadRegistered(this);
        });

        $('rrRegisteredSearch').addEventListener('input', function() {
            state.search = this.value;
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

        $('rrManageVerbs').addEventListener('click', function() {
            showMessage('Pilih endpoint pada tabel untuk mengatur HTTP verbs.', 'info');
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

        $('rrLoadPermissionComparison').addEventListener('click', function() {
            loadPermissionComparison(this);
        });

        $('rrSyncShieldPermissions').addEventListener('click', function() {
            syncShieldPermissions(this);
        });

        loadRegistered();
    })();
</script>
<?php $this->endSection() ?>
