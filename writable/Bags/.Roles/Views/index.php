<?php

declare(strict_types=1);

$apiBase = rtrim(site_url('api/v1/roles'), '/');
?>

<div class="py-4" id="routeRegistryApp" data-api-base="<?= esc($apiBase, 'attr') ?>">

    <!-- Page Header & Metrics Summary -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12 col-lg-4">
            <h4 class="mb-1 fw-bold text-dark">Route Registry</h4>
            <p class="text-muted mb-0 small">
                Inventaris endpoint, konfigurasi HTTP verbs, dan status routing sistem.
            </p>
        </div>
        <div class="col-12 col-lg-8">
            <div class="row g-2">
                <div class="col-4">
                    <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                        <div class="card-body p-3">
                            <div class="text-muted small mb-1"><i class="ti ti-search text-primary me-1"></i> Ditemukan</div>
                            <h4 class="mb-0 fw-semibold" id="rrDiscoveredCount">—</h4>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                        <div class="card-body p-3">
                            <div class="text-muted small mb-1"><i class="ti ti-database text-success me-1"></i> Terdaftar</div>
                            <h4 class="mb-0 fw-semibold" id="rrRegisteredCount">—</h4>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                        <div class="card-body p-3">
                            <div class="text-muted small mb-1"><i class="ti ti-route text-info me-1"></i> Routable</div>
                            <h4 class="mb-0 fw-semibold" id="rrRoutableCount">—</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="rrAlert" class="alert d-none shadow-sm" role="alert"></div>

    <!-- Main Card Container -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3">
            <!-- Nav Pills (Modern Style) -->
            <ul class="nav nav-pills gap-1 mb-0" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active px-3 py-2"
                        id="rrRegisteredTab"
                        data-bs-toggle="tab"
                        data-bs-target="#rrRegisteredPane"
                        role="tab">
                        <i class="ti ti-database me-1"></i> Registered Endpoints
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link px-3 py-2"
                        id="rrScanTab"
                        data-bs-toggle="tab"
                        data-bs-target="#rrScanPane"
                        role="tab">
                        <i class="ti ti-eye me-1"></i> Scan Preview
                    </a>
                </li>

            </ul>
            <div>

                <!-- Tombol Scan di Paling Kanan -->
                <button type="button" class="btn btn-primary btn-sm px-3 py-2" id="rrScanBtn">
                    <i class="ti ti-refresh me-1"></i> Scan Modul
                </button>

                <!-- Tombol Scan di Paling Kanan -->
                <button type="button" class="btn btn-warning btn-sm px-3 py-2" id="btnSyncShieldPermissions">
                    <i class="ti ti-refresh me-1"></i> Sinkronkan Permission ke Shield
                </button>

            </div>
        </div>

        <div class="card-body p-4">
            <div class="tab-content">
                <!-- REGISTERED ENDPOINTS PANE -->
                <div class="tab-pane fade  show active" id="rrRegisteredPane" role="tabpanel" aria-labelledby="rrRegisteredTab">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h6 class="mb-0 fw-semibold">Endpoint Tersimpan</h6>
                            <span class="text-muted small">Kelola konfigurasi registry yang tersimpan di dalam database.</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="rrReloadRegistered">
                                <i class="ti ti-reload me-1"></i> Muat Ulang
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="ti ti-search text-muted"></i></span>
                                <input type="search" class="form-control border-start-0 ps-0" id="rrRegisteredSearch" placeholder="Cari endpoint terdaftar...">
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <select class="form-select" id="rrPerPage">
                                <option value="10">10 per halaman</option>
                                <option value="25" selected>25 per halaman</option>
                                <option value="50">50 per halaman</option>
                                <option value="100">100 per halaman</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-2">
                        <table class="table table-hover align-middle table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Module</th>
                                    <th>Endpoint</th>
                                    <th>Permissions</th>
                                    <th>HTTP Verbs</th>
                                    <th class="text-center">Routable</th>
                                    <th class="text-center">Allowed</th>
                                    <th>Scan</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="rrRegisteredBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Memuat registry...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
                        <span class="small text-muted" id="rrRegisteredSummary"></span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="rrPrevPage">Sebelumnya</button>
                            <span class="small fw-medium" id="rrPageLabel">Halaman 1</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="rrNextPage">Berikutnya</button>
                        </div>
                    </div>
                </div>

                <!-- SCAN PREVIEW PANE -->
                <div class="tab-pane fade" id="rrScanPane" role="tabpanel" aria-labelledby="rrScanTab">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h6 class="mb-0 fw-semibold">Hasil Pemindaian Modul</h6>
                            <span class="text-muted small">Scan tidak langsung mengubah basis data sebelum Anda menerapkannya.</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="rrScanSelectAll">
                                <i class="ti ti-checkbox me-1"></i> Pilih Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="rrScanClear">
                                <i class="ti ti-square me-1"></i> Batal Pilih
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="ti ti-search text-muted"></i></span>
                                <input type="search" class="form-control border-start-0 ps-0" id="rrScanSearch" placeholder="Cari module, controller, method, endpoint...">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <select class="form-select" id="rrScanFilter">
                                <option value="all">Semua hasil</option>
                                <option value="new">Belum terdaftar</option>
                                <option value="registered">Sudah terdaftar</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <select class="form-select" id="rrApplyAction">
                                <option value="insert">Insert endpoint baru</option>
                                <option value="update">Update endpoint terdaftar</option>
                                <option value="delete">Delete endpoint terdaftar</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-2">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px" class="ps-3">
                                        <input type="checkbox" class="form-check-input" id="rrScanHeaderCheck" aria-label="Pilih semua">
                                    </th>
                                    <th>Module</th>
                                    <th>Controller&Method</th>
                                    <th>Verbs</th>
                                    <th class="text-center">Routable</th>
                                    <th class="text-center">Allowed</th>
                                </tr>
                            </thead>
                            <tbody id="rrScanBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ti ti-info-circle fs-3 d-mb-2"></i>
                                        <div>Klik tombol <strong>Scan Modul</strong> di kanan atas untuk memulai.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="small text-muted" id="rrScanSummary">Belum ada hasil scan.</span>
                        <button type="button" class="btn btn-primary btn-sm px-4" id="rrApplyBtn" disabled>
                            Terapkan Pilihan
                        </button>
                    </div>
                </div>



            </div>
        </div>
    </div>
</div>

<!-- HTTP VERBS MODAL -->
<div class="modal fade" id="rrVerbsModal" tabindex="-1"
    aria-labelledby="rrVerbsTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="rrVerbsTitle">HTTP verbs</h5>
                    <div class="small text-muted" id="rrVerbsEndpoint"></div>
                </div>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rrVerbsRouteId">
                <div id="rrVerbsOptions" class="d-flex flex-wrap gap-3">
                    <?php foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb): ?>
                        <label class="form-check">
                            <input class="form-check-input rr-verb-check"
                                type="checkbox"
                                value="<?= esc($verb, 'attr') ?>">
                            <span class="form-check-label"><?= esc($verb) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-3">
                    Pilih metode HTTP yang diizinkan untuk endpoint ini.
                    Konfigurasi ini bukan pengganti otorisasi permission pengguna.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary"
                    id="rrSaveVerbs">Simpan verbs</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rrShieldPreviewModal" tabindex="-1"
    aria-labelledby="rrShieldPreviewTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rrShieldPreviewTitle">
                    Preview Sinkronisasi Permission
                </h5>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted">
                    Periksa dan edit deskripsi sebelum menyimpan ke Shield.
                </p>

                <div class="d-flex gap-3 mb-3 flex-wrap">
                    <span>Jumlah: <strong id="rrPreviewCount">0</strong></span>
                    <span>Baru: <strong id="rrPreviewNew">0</strong></span>
                    <span>Sudah ada: <strong id="rrPreviewExisting">0</strong></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 28%">Permission</th>
                                <th>Deskripsi (dapat diedit)</th>
                                <th style="width: 100px">Status</th>
                            </tr>
                        </thead>
                        <tbody id="rrShieldPreviewBody">
                            <tr>
                                <td colspan="3" class="text-center">
                                    Belum ada data preview.
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
                <button type="button" class="btn btn-primary"
                    id="rrConfirmShieldSync">
                    Konfirmasi Sinkronisasi
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->section('jsFooterInline'); ?>
<script>
    (() => {
        'use strict';

        const root = document.getElementById('routeRegistryApp');
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const API = root.dataset.apiBase;
        const $ = id => document.getElementById(id);

        const state = {
            scanItems: [],
            selectedScan: new Set(),
            registeredItems: [],
            page: 1,
            perPage: 25,
            total: 0,
            totalPages: 1,
            searchTimer: null,
            verbsRoute: null,
            verbsModal: null
        };

        const allowedVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        function showAlert(message, type = 'success') {
            const el = $('rrAlert');
            el.className = `alert alert-${type}`;
            el.textContent = message;
            el.classList.remove('d-none');
        }

        function hideAlert() {
            $('rrAlert').classList.add('d-none');
        }

        function setBusy(button, busy, busyText = 'Memproses...') {
            if (!button) return;
            if (busy) {
                button.dataset.originalText = button.textContent;
                button.disabled = true;
                button.textContent = busyText;
            } else {
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                    delete button.dataset.originalText;
                }
            }
        }

        function getCsrf() {
            const name = document.querySelector('meta[name="csrf-header"]')?.content;
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            if (name && token) return {
                name,
                token
            };

            // Fallback untuk konfigurasi standar CodeIgniter.
            const fallbackName = document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content;
            if (fallbackName) return {
                name: 'X-CSRF-TOKEN',
                token: fallbackName
            };

            return null;
        }

        async function request(path, options = {}) {
            const method = (options.method || 'GET').toUpperCase();
            const headers = {
                'Accept': 'application/json',
                ...(options.headers || {})
            };

            if (options.body !== undefined) {
                headers['Content-Type'] = 'application/json';
            }

            const csrf = getCsrf();
            if (csrf && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                headers[csrf.name] = csrf.token;
            }

            const response = await fetch(`${API}/${path.replace(/^\/+/, '')}`, {
                ...options,
                method,
                headers,
                credentials: 'same-origin'
            });

            const newToken = response.headers.get('X-CSRF-TOKEN');
            if (newToken) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = newToken;
            }

            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error(`Server mengembalikan respons bukan JSON (HTTP ${response.status}).`);
            }

            if (result?.csrf_token) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = result.csrf_token;
            }

            if (!response.ok || result?.status === 'error' || result?.status === 'failed') {
                throw new Error(result?.message || `Permintaan gagal (HTTP ${response.status}).`);
            }

            return result;
        }

        function payloadOf(result) {
            return result?.data ?? result;
        }

        function itemsOf(result) {
            const data = payloadOf(result);
            if (Array.isArray(data)) return data;
            if (Array.isArray(data?.items)) return data.items;
            return [];
        }

        function routeId(item) {
            return Number(item?.registry?.id ?? item?.id ?? 0);
        }

        function endpointOf(item) {
            return String(item?.endpoint ?? item?.registry?.endpoint ?? '');
        }

        function registryOf(item) {
            return item?.registry && typeof item.registry === 'object' ?
                item.registry :
                item;
        }

        function badge(text, color = 'secondary', pill = false, property = "") {
            if (pill) {
                return `<span class="badge bg-${color}-subtle text-${color}  rounded-pill px-2 py-1">
            <span class="round-8 text-bg-${color} rounded-circle d-inline-block me-1"></span>${escapeHtml(text)}
        </span>`;
            }

            return `<span class="badge bg-${color}-subtle ${property} text-${color}">${escapeHtml(text)}</span>`;
        }

        function icon(icon = 'bi bi-square', color = "text-muted", button = false) {
            const iconHtml = `<i class="${icon} ${color}"></i>`;

            if (button) {
                return `<button type="button" class="btn p-0 border-0 bg-transparent">${iconHtml}</button>`;
            }

            return iconHtml;
        }

        function boolBadge(value) {
            return Number(value) === 1 || value === true ?
                badge('Aktif', 'success') :
                badge('Nonaktif', 'secondary');
        }

        function updateCounters() {
            $('rrDiscoveredCount').textContent = state.scanItems.length;

            const registered = state.registeredItems;
            $('rrRegisteredCount').textContent = state.total;

            const routableCount = registered.filter(item => {
                const registry = registryOf(item);
                return Number(registry.is_routable) === 1;
            }).length;

            $('rrRoutableCount').textContent =
                state.totalPages > 1 ? `${routableCount}*` : routableCount;
        }

        function visibleScanItems() {
            const search = $('rrScanSearch').value.trim().toLowerCase();
            const filter = $('rrScanFilter').value;

            return state.scanItems.filter(item => {
                const registry = registryOf(item);
                const registered = item.state === 'registered' || Number(registry.id) > 0;

                if (filter === 'new' && registered) return false;
                if (filter === 'registered' && !registered) return false;

                const haystack = [
                    item.module, item.controller, item.method, item.endpoint, item.url_route
                ].join(' ').toLowerCase();

                return !search || haystack.includes(search);
            });
        }

        function renderScan() {
            const tbody = $('rrScanBody');
            if (!tbody) return;

            // Hormati pencarian dan filter yang sudah ada.
            const items = visibleScanItems();
            const selectedAction = $('rrApplyAction').value;

            const isRegistered = item => {
                const registry = registryOf(item);
                return item.state === 'registered' ||
                    Number(registry?.id) > 0;
            };

            const getVerbs = item => {
                const registry = registryOf(item);
                const verbs = registry?.verbs;

                if (!Array.isArray(verbs) || verbs.length === 0) {
                    return '—';
                }

                return verbs
                    .map(verb => typeof verb === 'string' ?
                        verb :
                        (verb?.verb ?? ''))
                    .filter(Boolean)
                    .join(', ');
            };

            if (items.length === 0) {
                tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Tidak ada endpoint yang cocok.
                </td>
            </tr>`;
            } else {
                tbody.innerHTML = items.map(item => {
                    const endpoint = endpointOf(item);
                    const registry = registryOf(item);
                    const registered = isRegistered(item);
                    const checked = state.selectedScan.has(endpoint);

                    let status = registered ?
                        badge('Terdaftar', 'success') :
                        badge('Baru', 'info');

                    if (selectedAction === 'insert' && registered) {
                        status += ` ${badge('Akan dilewati', 'warning')}`;
                    } else if (selectedAction === 'update' && !registered) {
                        status += ` ${badge('Belum terdaftar', 'warning')}`;
                    }

                    const statusCheckbox = (field, checkedValue) => `
                <input
                    type="checkbox"
                    class="form-check-input rr-scan-status-toggle"
                    data-id="${registered ? Number(registry.id) : ''}"
                    data-index="${state.scanItems.indexOf(item)}"
                    data-field="${field}"
                    ${checkedValue ? 'checked' : ''}
                    ${registered ? '' : 'disabled'}
                    aria-label="${field === 'routable' ? 'Routable' : 'Allowed'}">
            `;

                    return `
                <tr>
                    <td class="ps-3">
                        <input
                            type="checkbox"
                            class="form-check-input rr-scan-check"
                            data-endpoint="${escapeHtml(endpoint)}"
                            ${checked ? 'checked' : ''}
                            aria-label="Pilih endpoint">
                    </td>
                    <td>${escapeHtml(item.module || '—')}</td>
                    <td class="text-break">
                        <div>${escapeHtml(item.controller || '—')}</div>
                        <small class="text-muted">
                            <code>${escapeHtml(endpoint)}</code>
                        </small>
                        <div class="mt-1">${status}</div>
                    </td>
                    <td>${escapeHtml(getVerbs(item))}</td>
                    <td class="text-center">
                        ${registered
                            ? statusCheckbox(
                                'routable',
                                Number(registry.is_routable) === 1
                            )
                            : icon()}
                    </td>
                    <td class="text-center">
                        ${registered
                            ? statusCheckbox(
                                'allowed',
                                Number(registry.is_allowed) === 1
                            )
                            : icon()}
                    </td>
                </tr>`;
                }).join('');
            }

            // Ringkasan tindakan Apply.
            const eligible = items.filter(item => {
                const registered = isRegistered(item);

                if (selectedAction === 'insert') return !registered;
                if (selectedAction === 'update') return registered;

                // Pertahankan perilaku lama untuk tindakan lainnya.
                return registered;
            });

            const selectedEligible = eligible.filter(item =>
                state.selectedScan.has(endpointOf(item))
            ).length;

            $('rrScanSummary').textContent =
                `${items.length} ditampilkan dari ${state.scanItems.length} hasil; ` +
                `${state.selectedScan.size} dipilih, ` +
                `${selectedEligible} sesuai tindakan.`;

            $('rrApplyBtn').disabled = selectedEligible === 0;

            // Header hanya mencerminkan item yang sedang terlihat.
            $('rrScanHeaderCheck').checked =
                items.length > 0 &&
                items.every(item =>
                    state.selectedScan.has(endpointOf(item))
                );
        }



        async function scan() {
            hideAlert();
            const button = $('rrScanBtn');
            setBusy(button, true, 'Memindai...');

            $('rrScanBody').innerHTML =
                '<tr><td colspan="6" class="text-center py-4">Sedang memindai modul...</td></tr>';

            try {
                const result = await request('scan');
                const data = payloadOf(result);
                state.scanItems = Array.isArray(data?.items) ? data.items : itemsOf(result);
                state.selectedScan.clear();

                renderScan();
                updateCounters();

                showAlert(
                    result.message || `Scan selesai. ${state.scanItems.length} endpoint ditemukan.`
                );
            } catch (error) {
                $('rrScanBody').innerHTML =
                    `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }

        function isEligibleForAction(item, action) {
            const registered = item.state === 'registered' ||
                Number(registryOf(item).id) > 0;

            if (action === 'insert') return !registered;
            if (action === 'update') return registered;
            if (action === 'delete') return registered;

            return false;
        }

        async function applySelected() {
            hideAlert();

            const action = $('rrApplyAction').value;
            const selected = [...state.selectedScan];

            if (!selected.length) {
                showAlert('Pilih minimal satu endpoint.', 'warning');
                return;
            }

            const eligible = state.scanItems.filter(item => {
                if (!state.selectedScan.has(endpointOf(item))) return false;
                const registered = item.state === 'registered' ||
                    Number(registryOf(item).id) > 0;

                if (action === 'insert') return !registered;
                if (action === 'update') return registered;
                return registered;
            });

            const endpoints = eligible.map(endpointOf);

            if (!endpoints.length) {
                showAlert('Tidak ada endpoint terpilih yang sesuai dengan tindakan ini.', 'warning');
                return;
            }

            const actionLabel = {
                insert: 'menambahkan',
                update: 'memperbarui',
                delete: 'menghapus'
            } [action];

            if (!window.confirm(`Yakin ${actionLabel} ${endpoints.length} endpoint?`)) return;

            const button = $('rrApplyBtn');
            setBusy(button, true, 'Menerapkan...');
            const url_route = Object.fromEntries(
                eligible
                .filter(item =>
                    typeof item.url_route === 'string' &&
                    item.url_route.trim() !== ''
                )
                .map(item => [
                    endpointOf(item),
                    item.url_route.trim()
                ])
            );
            const permissions = Object.fromEntries(
                eligible
                .filter(item =>
                    typeof item.permission === 'string' &&
                    item.permission.trim() !== ''
                )
                .map(item => [
                    endpointOf(item),
                    item.permission.trim()
                ])
            );
            try {
                const result = await request('apply', {
                    method: 'POST',
                    body: JSON.stringify({
                        action,
                        selected: endpoints,
                        url_route,
                        permissions
                    })
                });

                state.selectedScan.clear();

                showAlert(result.message || 'Perubahan registry berhasil diproses.');
                await loadRegistered();

                // Perbarui hasil scan agar status mencerminkan database terbaru.
                await scan();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
                renderScan();
            }
        }

        async function loadRegistered() {
            const body = $('rrRegisteredBody');
            body.innerHTML =
                '<tr><td colspan="7" class="text-center py-4">Memuat data...</td></tr>';

            try {
                const params = new URLSearchParams({
                    page: String(state.page),
                    perPage: String(state.perPage),
                    search: $('rrRegisteredSearch').value.trim()
                });

                const result = await request(`registered?${params.toString()}`);
                const data = payloadOf(result);

                state.registeredItems = Array.isArray(data?.items) ? data.items : [];
                const pagination = data?.pagination ?? {};

                state.total = Number(pagination.total ?? data?.total ?? state.registeredItems.length);
                state.page = Number(pagination.page ?? state.page);
                state.perPage = Number(pagination.perPage ?? state.perPage);
                state.totalPages = Number(
                    pagination.totalPages ??
                    pagination.pageCount ??
                    Math.max(1, Math.ceil(state.total / state.perPage))
                );

                renderRegistered();
                updateCounters();
            } catch (error) {
                body.innerHTML =
                    `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
                $('rrRegisteredSummary').textContent = 'Gagal memuat registry.';
                showAlert(error.message, 'danger');
            }
        }

        function renderRegistered() {
            const items = state.registeredItems;

            $('rrRegisteredBody').innerHTML = items.length ?
                items.map(item => {
                    const registry = registryOf(item);
                    const id = routeId(item);
                    const endpoint = endpointOf(item);
                    const verbs = Array.isArray(registry.verbs) ?
                        registry.verbs.map(v => String(v).toUpperCase()) : [];
                    const permissions = Array.isArray(registry.permissions) ?
                        registry.permissions.map(v => String(v)) : [];
                    const url = registry.url;
                    return `
                    <tr>
                        <td class="small text-muted">${escapeHtml(item.module ?? registry.module)}</td>
                        <td class="small text-break">
                            <code>${escapeHtml(endpoint)}</code>
                            <div class="small text-muted">
                                ${escapeHtml(url ?? registry.controller)}::
                                ${escapeHtml(item.method ?? registry.url_route)}
                            </div>
                        </td>
                        <td class="small text-muted">
                            <div class="">
                                ${permissions.length
                                    ? permissions.map(v => v).join('')
                                    : badge('Belum diatur', 'warning')}
                            </div>
                        </td>
                        <td class="small">
                            <div class="d-flex flex-wrap h6 gap-1  mb-1">
                                ${verbs.length
                                    ? verbs.map(v => badge(v, 'info')).join('')
                                    : ''}

                            </div>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary rr-edit-verbs"
                                    data-id="${id}"
                                    data-endpoint="${escapeHtml(endpoint)}"
                                    ${id < 1 ? 'disabled' : ''}>
                                <i class="bi bi-sliders"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input rr-status-toggle"
                                       type="checkbox"
                                       data-id="${id}"
                                       data-field="routable"
                                       ${Number(registry.is_routable) === 1 ? 'checked' : ''}
                                       ${id < 1 ? 'disabled' : ''}>
                            </label>
                        </td>
                        <td class="text-center">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input rr-status-toggle"
                                       type="checkbox"
                                       data-id="${id}"
                                       data-field="allowed"
                                       ${Number(registry.is_allowed) === 1 ? 'checked' : ''}
                                       ${id < 1 ? 'disabled' : ''}>
                            </label>
                        </td>
                        <td class="text-center">${(() => {
                            const status = String(registry.scan_status ?? 'unknown').trim().toLowerCase();
                            const map = {
                                scanned: ['ti ti-scan', 'text-success fa-2x'],
                                new: ['ti ti-sparkles', 'text-primary'],
                                unknown: ['ti ti-circle-x', 'text-secondary']
                            };
                            const [label, color] = map[status] || ['Unknown', 'secondary'];
                            return icon(label, color, true);
                        })()}
                        </td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn p-0 border-0 btn-sm btn-outline-secondary rr-copy-endpoint"
                                    data-endpoint="${escapeHtml(endpoint)}">
                                <i class="ti ti-copy fa-2x"></i>
                            </button>
                        </td>
                    </tr>`;
                }).join('') :
                `<tr><td colspan="7" class="text-center text-muted py-4">
                    Belum ada endpoint terdaftar.
               </td></tr>`;

            $('rrRegisteredSummary').textContent =
                `${items.length} endpoint pada halaman ini; total ${state.total}.`;

            $('rrPageLabel').textContent = `Halaman ${state.page} / ${state.totalPages}`;
            $('rrPrevPage').disabled = state.page <= 1;
            $('rrNextPage').disabled = state.page >= state.totalPages;
        }

        async function openVerbs(id, endpoint) {
            hideAlert();

            try {
                const result = await request(`getVerbs/${encodeURIComponent(id)}`);
                const data = payloadOf(result);

                const verbs = Array.isArray(data?.verbs) ?
                    data.verbs.map(v => String(v).toUpperCase()) : [];

                $('rrVerbsRouteId').value = String(id);
                $('rrVerbsEndpoint').textContent = endpoint;

                document.querySelectorAll('.rr-verb-check').forEach(input => {
                    input.checked = verbs.includes(input.value);
                });

                state.verbsRoute = {
                    id,
                    endpoint
                };

                if (!state.verbsModal) {
                    if (!window.bootstrap?.Modal) {
                        throw new Error('Bootstrap Modal tidak tersedia pada halaman ini.');
                    }
                    state.verbsModal = new bootstrap.Modal($('rrVerbsModal'));
                }

                state.verbsModal.show();
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        }

        async function saveVerbs() {
            if (!state.verbsRoute) return;

            const verbs = [...document.querySelectorAll('.rr-verb-check:checked')]
                .map(input => input.value)
                .filter(verb => allowedVerbs.includes(verb));

            const button = $('rrSaveVerbs');
            setBusy(button, true, 'Menyimpan...');

            try {
                const result = await request('saveVerbs', {
                    method: 'POST',
                    body: JSON.stringify({
                        endpoint: state.verbsRoute.endpoint,
                        verbs
                    })
                });

                state.verbsModal?.hide();
                showAlert(result.message || 'HTTP verbs berhasil disimpan.');
                await loadRegistered();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                setBusy(button, false);
            }
        }


        async function updateStatus(input) {
            const id = Number(input.dataset.id);
            const field = input.dataset.field;
            const value = input.checked;
            const previousValue = !input.checked;

            // Validasi sebelum mengirim permintaan.
            if (!Number.isInteger(id) || id < 1) {
                input.checked = previousValue;
                console.error('ID endpoint tidak valid:', input.dataset.id);
                return;
            }

            if (!['routable', 'allowed'].includes(field)) {
                input.checked = previousValue;
                console.error('Field status tidak valid:', field);
                return;
            }

            // Simpan referensi untuk sinkronisasi Scan Preview.
            const scanIndex = input.dataset.index;
            const isScanToggle = input.classList.contains(
                'rr-scan-status-toggle'
            );

            input.disabled = true;
            // console.log([id, field, value]);
            try {
                // Penyimpanan permanen dilakukan oleh API.
                const response = await request('updateStatus', {
                    method: 'PATCH',
                    body: JSON.stringify({
                        id,
                        field,
                        value
                    })
                });

                // Jangan mengubah cache sebelum API sukses.
                if (response?.status === false || response?.error) {
                    throw new Error(
                        response.message || 'Gagal menyimpan status.'
                    );
                }

                // Sinkronkan cache Scan Preview di memori browser.
                if (isScanToggle && scanIndex !== undefined) {
                    const item = state.scanItems?.[Number(scanIndex)];

                    if (item?.registry) {
                        const registryField = field === 'routable' ?
                            'is_routable' :
                            'is_allowed';

                        item.registry[registryField] = value;
                    }
                }

                // Muat ulang daftar Registered Endpoints dari server.
                await loadRegistered();

                // Perbarui tampilan Scan Preview.
                if (isScanToggle) {
                    renderScan();
                }
            } catch (error) {
                // Kembalikan checkbox ke kondisi sebelumnya.
                input.checked = previousValue;

                console.error('Update status gagal:', error);

                if (typeof showToast === 'function') {
                    showToast(
                        error.message || 'Gagal menyimpan status.',
                        'error'
                    );
                } else {
                    alert(error.message || 'Gagal menyimpan status.');
                }
            } finally {
                // renderScan() bisa membuat elemen checkbox baru.
                // Elemen lama tidak perlu diaktifkan kembali.
                if (input.isConnected) {
                    input.disabled = false;
                }
            }
        }


        // Scan controls.
        $('rrScanBtn').addEventListener('click', scan);
        $('rrApplyBtn').addEventListener('click', applySelected);
        $('rrScanSearch').addEventListener('input', renderScan);
        $('rrScanFilter').addEventListener('change', renderScan);
        $('rrApplyAction').addEventListener('change', renderScan);

        $('rrScanSelectAll').addEventListener('click', () => {
            visibleScanItems().forEach(item => state.selectedScan.add(endpointOf(item)));
            renderScan();
        });

        $('rrScanClear').addEventListener('click', () => {
            state.selectedScan.clear();
            renderScan();
        });

        $('rrScanHeaderCheck').addEventListener('change', event => {
            visibleScanItems().forEach(item => {
                const endpoint = endpointOf(item);
                if (event.target.checked) state.selectedScan.add(endpoint);
                else state.selectedScan.delete(endpoint);
            });
            renderScan();
        });


        $('rrScanBody').addEventListener('change', event => {
            const statusInput = event.target.closest('.rr-scan-status-toggle');

            if (statusInput) {
                updateStatus(statusInput);
                return;
            }

            const input = event.target.closest('.rr-scan-check');
            if (!input) return;

            const endpoint = input.dataset.endpoint;
            if (!endpoint) return;

            if (input.checked) {
                state.selectedScan.add(endpoint);
            } else {
                state.selectedScan.delete(endpoint);
            }

            renderScan();
        });


        // Registered controls.
        $('rrReloadRegistered').addEventListener('click', loadRegistered);

        $('rrRegisteredSearch').addEventListener('input', () => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(() => {
                state.page = 1;
                loadRegistered();
            }, 300);
        });

        $('rrPerPage').addEventListener('change', () => {
            state.perPage = Number($('rrPerPage').value) || 25;
            state.page = 1;
            loadRegistered();
        });

        $('rrPrevPage').addEventListener('click', () => {
            if (state.page > 1) {
                state.page--;
                loadRegistered();
            }
        });

        $('rrNextPage').addEventListener('click', () => {
            if (state.page < state.totalPages) {
                state.page++;
                loadRegistered();
            }
        });



        $('rrRegisteredBody').addEventListener('click', async event => {
            const verbsButton = event.target.closest('.rr-edit-verbs');

            if (verbsButton) {
                await openVerbs(
                    Number(verbsButton.dataset.id),
                    verbsButton.dataset.endpoint
                );
                return;
            }

            const copyButton = event.target.closest('.rr-copy-endpoint');
            if (!copyButton) return;

            const endpoint = copyButton.dataset.endpoint;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    // Browser modern.
                    await navigator.clipboard.writeText(endpoint);
                } else {
                    // Browser lama yang tidak mendukung Clipboard API.
                    throw new Error('Clipboard API tidak didukung.');
                }

                showAlert('Endpoint berhasil disalin.', 'success');
            } catch (error) {
                if (!navigator.clipboard || !window.isSecureContext) {
                    // Fallback browser lama.
                    const textarea = document.createElement('textarea');
                    textarea.value = endpoint;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';

                    document.body.appendChild(textarea);
                    textarea.select();

                    try {
                        if (document.execCommand('copy')) {
                            showAlert('Endpoint berhasil disalin.', 'success');
                        } else {
                            showAlert(
                                'Gagal menyalin endpoint: ' + endpoint,
                                'warning'
                            );
                        }
                    } finally {
                        textarea.remove();
                    }
                } else {
                    console.error('Gagal menyalin endpoint:', error);
                    showAlert(
                        'Akses clipboard ditolak oleh browser.',
                        'warning'
                    );
                }
            }
        });



        $('rrRegisteredBody').addEventListener('change', event => {
            const input = event.target.closest('.rr-status-toggle');
            if (input) updateStatus(input);
        });


        document.addEventListener('change', async (event) => {
            const input = event.target.closest(
                '.rr-status-toggle, .rr-scan-status-toggle'
            );

            if (!input) return;

            // Endpoint baru belum punya ID database.
            if (input.disabled || !input.dataset.id) return;

            // Gunakan fungsi updateStatus yang sudah dipakai
            // oleh tabel Registered Endpoints.
            await updateStatus(input);

        });


        $('rrSaveVerbs').addEventListener('click', saveVerbs);

        // Tab registered: load data saat pertama dibuka.
        $('rrRegisteredTab').addEventListener('shown.bs.tab', () => {
            loadRegistered();
        });


        (() => {
            const button = document.getElementById('btnSyncShieldPermissions');
            const confirmButton = document.getElementById('rrConfirmShieldSync');
            const modalElement = document.getElementById('rrShieldPreviewModal');

            if (!button || !confirmButton || !modalElement) return;

            const app = document.getElementById('routeRegistryApp');
            const apiBase = app.dataset.apiBase;
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

            let previewItems = [];

            async function request(url, options = {}) {
                const response = await apiFetch(url, options);
                const result = response.data;

                if (!response.ok || result?.status !== 'success') {
                    throw new Error(
                        result?.message ||
                        `Permintaan gagal (HTTP ${response.status}).`
                    );
                }

                return result.data ?? {};
            }

            function renderPreview(items) {
                const tbody = document.getElementById('rrShieldPreviewBody');
                tbody.replaceChildren();

                document.getElementById('rrPreviewCount').textContent = items.length;
                document.getElementById('rrPreviewNew').textContent =
                    items.filter(item => !item.exists).length;
                document.getElementById('rrPreviewExisting').textContent =
                    items.filter(item => item.exists).length;

                if (!items.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');

                    td.colSpan = 3;
                    td.className = 'text-center text-muted';
                    td.textContent = 'Tidak ada permission yang dapat disinkronkan.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);

                    confirmButton.disabled = true;
                    return;
                }

                confirmButton.disabled = false;

                items.forEach((item, index) => {
                    const tr = document.createElement('tr');

                    const permissionCell = document.createElement('td');
                    const code = document.createElement('code');
                    code.textContent = item.permission;
                    permissionCell.appendChild(code);

                    const descriptionCell = document.createElement('td');
                    const textarea = document.createElement('textarea');

                    textarea.className = 'form-control';
                    textarea.rows = 2;
                    textarea.maxLength = 255;
                    textarea.value = item.description ?? '';
                    textarea.dataset.index = String(index);
                    textarea.setAttribute(
                        'aria-label',
                        `Deskripsi permission ${item.permission}`
                    );

                    descriptionCell.appendChild(textarea);

                    const statusCell = document.createElement('td');
                    const badge = document.createElement('span');

                    badge.className = item.exists ?
                        'badge bg-secondary' :
                        'badge bg-primary';
                    badge.textContent = item.exists ? 'Sudah ada' : 'Baru';

                    statusCell.appendChild(badge);

                    tr.append(permissionCell, descriptionCell, statusCell);
                    tbody.appendChild(tr);
                });
            }

            // Tahap 1: tombol membuka preview, tidak langsung menyimpan.
            button.addEventListener('click', async () => {
                button.disabled = true;

                try {
                    const data = await request(
                        `${apiBase}/previewShieldPermissions`, {
                            method: 'GET'
                        }
                    );

                    previewItems = Array.isArray(data) ? data : [];
                    renderPreview(previewItems);
                    modal.show();
                } catch (error) {
                    alert(error.message || 'Gagal memuat preview permission.');
                } finally {
                    button.disabled = false;
                }
            });

            // Tahap 2: ambil deskripsi hasil edit dan kirim untuk disimpan.
            confirmButton.addEventListener('click', async () => {
                const textareas = modalElement.querySelectorAll(
                    '#rrShieldPreviewBody textarea'
                );

                const items = previewItems.map((item, index) => ({
                    permission: item.permission,
                    description: textareas[index]?.value.trim() ?? ''
                }));

                const invalid = items.find(item =>
                    !item.permission ||
                    !item.description ||
                    item.description.length > 255
                );

                if (invalid) {
                    alert(
                        'Setiap permission harus memiliki deskripsi 1–255 karakter.'
                    );
                    return;
                }

                if (!confirm(
                        `Simpan ${items.length} permission ke Shield dengan deskripsi ini?`
                    )) {
                    return;
                }

                confirmButton.disabled = true;
                button.disabled = true;

                try {
                    const result = await request(
                        `${apiBase}/syncShieldPermissions`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                items
                            })
                        }
                    );

                    modal.hide();

                    alert(
                        'Sinkronisasi berhasil.\n' +
                        `Ditambahkan: ${result.inserted ?? 0}\n` +
                        `Diperbarui: ${result.updated ?? 0}\n` +
                        `Sudah ada: ${result.existing ?? 0}\n` +
                        `Dilewati: ${result.skipped ?? 0}`
                    );
                } catch (error) {
                    alert(error.message || 'Sinkronisasi gagal.');
                } finally {
                    confirmButton.disabled = false;
                    button.disabled = false;
                }
            });
        })();

        document
            .getElementById('btnSyncShieldPermissions222')
            ?.addEventListener('click', async function() {
                if (!confirm('Sinkronkan permission Route Registry ke Shield?')) {
                    return;
                }

                const button = this;
                button.disabled = true;

                try {
                    const response = await apiFetch(
                        '<?= site_url('api/V1/route-registry/sync-shield-permissions') ?>', {
                            method: 'POST'
                        }
                    );

                    const result = await response.json();

                    if (!response.ok || result.status !== 'success') {
                        throw new Error(
                            result.message || 'Sinkronisasi gagal.'
                        );
                    }

                    const data = result.data || {};

                    alert(
                        `${result.message}\n` +
                        `Ditambahkan: ${data.inserted ?? 0}\n` +
                        `Sudah ada: ${data.existing ?? 0}\n` +
                        `Dilewati: ${data.skipped ?? 0}`
                    );
                } catch (error) {
                    alert(error.message || 'Terjadi kesalahan saat sinkronisasi.');
                } finally {
                    button.disabled = false;
                }
            });

        // Initial state.
        $('rrPerPage').value = String(state.perPage);
        loadRegistered();
    })();
</script>
<?= $this->endSection(); ?>
