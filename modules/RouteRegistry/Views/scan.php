<?php

declare(strict_types=1);

$activeView = 'scan';
$apiBase    = rtrim(site_url('api/v1/route-registry'), '/');
?>

<div id="routeRegistryScanApp" data-api-base="<?= esc($apiBase, 'attr') ?>">

    <?= $this->include('Modules\RouteRegistry\Views\partials\navigation') ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Scan Preview</h4>
            <p class="text-muted mb-0">
                Pindai endpoint modul, periksa hasil penemuan, lalu terapkan perubahan ke Route Registry.
            </p>
        </div>

        <button type="button" class="btn btn-primary" id="rrScanStart">
            <i class="ti ti-scan me-1"></i>
            Mulai Scan
        </button>
    </div>

    <div id="rrScanMessage" class="alert d-none" role="alert"></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Total Hasil Scan</div>
                    <h3 class="mb-0" id="rrScanTotal">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Endpoint Baru</div>
                    <h3 class="mb-0" id="rrScanNew">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Perubahan</div>
                    <h3 class="mb-0" id="rrScanChanged">0</h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-2">Tidak Ditemukan</div>
                    <h3 class="mb-0" id="rrScanMissing">0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1">Scan Results</h5>
                <p class="text-muted mb-0">
                    Periksa endpoint sebelum menerapkan hasil scan.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <input
                    type="search"
                    class="form-control"
                    id="rrScanSearch"
                    placeholder="Cari hasil scan..."
                    aria-label="Cari hasil scan"
                    style="min-width: 220px;">

                <button type="button" class="btn btn-outline-secondary" id="rrScanSelectAll">
                    Pilih Semua
                </button>

                <button type="button" class="btn btn-success" id="rrScanApply" disabled>
                    <i class="ti ti-check me-1"></i>
                    Apply Terpilih
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="rrScanCheckAll"
                                aria-label="Pilih semua endpoint">
                        </th>
                        <th style="min-width: 220px;">Endpoint</th>
                        <th style="min-width: 180px;">Module</th>
                        <th style="min-width: 220px;">Controller / Method</th>
                        <th>Status Scan</th>
                        <th class="text-center">Routable</th>
                        <th class="text-center">Allowed</th>
                    </tr>
                </thead>

                <tbody id="rrScanBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Klik Mulai Scan untuk menemukan endpoint.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted" id="rrScanInfo">Belum ada hasil scan.</small>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="rrScanPrev">
                    Sebelumnya
                </button>
                <span id="rrScanPage" class="small">1 / 1</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="rrScanNext">
                    Berikutnya
                </button>
            </div>
        </div>
    </div>
</div>

<?php $this->section('jsFooterInline') ?>
<script>
    (function() {
        'use strict';

        const app = document.getElementById('routeRegistryScanApp');

        if (!app || app.dataset.initialized === 'true') {
            return;
        }

        app.dataset.initialized = 'true';

        const apiBase = app.dataset.apiBase.replace(/\/+$/, '');
        const $ = (id) => document.getElementById(id);

        const state = {
            rows: [],
            filtered: [],
            selected: new Set(),
            page: 1,
            pageSize: 10,
            search: '',
            scanned: false,
            busy: false
        };

        /*
         * Nama endpoint mengikuti pola API Route Registry.
         * Cocokkan scan/apply dengan ApiRoutes.php aktual sebelum digunakan.
         */
        const api = {
            scan: apiBase + '/scan',
            apply: apiBase + '/apply'
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

        function headers() {
            const result = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            const meta = document.querySelector('meta[name="csrf-token"]');

            if (meta && meta.content) {
                result['X-CSRF-TOKEN'] = meta.content;
            }

            return result;
        }

        async function request(url, options = {}) {
            const config = {
                ...options,
                headers: {
                    ...headers(),
                    ...(options.headers || {})
                }
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
                    throw new Error('Respons server bukan JSON yang valid.');
                }
            }

            if (result.csrf_token) {
                const meta = document.querySelector('meta[name="csrf-token"]');

                if (meta) {
                    meta.content = result.csrf_token;
                }
            }

            if (!response.ok || result.status === false || result.error) {
                throw new Error(
                    result.message || 'Permintaan gagal (' + response.status + ').'
                );
            }

            return result;
        }

        function unwrap(result) {
            return result && result.data !== undefined ? result.data : result;
        }

        function extractRows(result) {
            const data = unwrap(result);

            if (Array.isArray(data)) {
                return data;
            }

            if (data && typeof data === 'object') {
                for (const key of ['items', 'results', 'scan', 'routes', 'endpoints']) {
                    if (Array.isArray(data[key])) {
                        return data[key];
                    }
                }
            }

            return [];
        }

        function rowId(row) {
            return String(
                row.id ??
                row.registry_id ??
                row.route_registry_id ??
                row.endpoint ??
                row.route ??
                ''
            );
        }

        function endpointName(row) {
            return row.endpoint ?? row.route ?? row.uri ?? row.path ?? '—';
        }

        function moduleName(row) {
            return row.module_name ?? row.module ?? row.registry_module ?? '—';
        }

        function handlerName(row) {
            const controller = row.controller ?? row.controller_name ?? '';
            const method = row.method ?? row.action ?? '';

            if (controller && method) {
                return controller + '::' + method;
            }

            return row.handler ?? row.class_name ?? controller ?? method ?? '—';
        }

        function enabled(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        }

        function scanStatus(row) {
            return String(
                row.scan_status ??
                row.status ??
                row.change_type ??
                'discovered'
            ).toLowerCase();
        }

        function statusBadge(status) {
            const styles = {
                new: 'bg-success-subtle text-success',
                discovered: 'bg-info-subtle text-info',
                changed: 'bg-warning-subtle text-warning',
                missing: 'bg-danger-subtle text-danger',
                unchanged: 'bg-secondary-subtle text-secondary',
                existing: 'bg-secondary-subtle text-secondary'
            };

            const labels = {
                new: 'Baru',
                discovered: 'Ditemukan',
                changed: 'Berubah',
                missing: 'Tidak ditemukan',
                unchanged: 'Tidak berubah',
                existing: 'Sudah terdaftar'
            };

            return '<span class="badge ' +
                (styles[status] || 'bg-secondary-subtle text-secondary') +
                '">' + escapeHtml(labels[status] || status) + '</span>';
        }

        function showMessage(message, type = 'info') {
            const box = $('rrScanMessage');

            box.className = 'alert alert-' + type;
            box.textContent = message;
            box.classList.remove('d-none');
        }

        function setBusy(busy, button = null, label = 'Memproses...') {
            state.busy = busy;

            $('rrScanStart').disabled = busy;
            $('rrScanApply').disabled = busy || state.selected.size === 0;

            if (button) {
                if (busy) {
                    button.dataset.originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span>' +
                        escapeHtml(label);
                } else {
                    button.disabled = false;

                    if (button.dataset.originalHtml !== undefined) {
                        button.innerHTML = button.dataset.originalHtml;
                        delete button.dataset.originalHtml;
                    }
                }
            }
        }

        function updateSummary() {
            const rows = state.rows;

            $('rrScanTotal').textContent = rows.length;
            $('rrScanNew').textContent = rows.filter((row) => ['new', 'discovered'].includes(scanStatus(row))).length;
            $('rrScanChanged').textContent = rows.filter((row) =>
                scanStatus(row) === 'changed'
            ).length;
            $('rrScanMissing').textContent = rows.filter((row) =>
                scanStatus(row) === 'missing'
            ).length;
        }

        function updatePagination() {
            const total = state.filtered.length;
            const pages = Math.max(1, Math.ceil(total / state.pageSize));

            state.page = Math.min(state.page, pages);

            $('rrScanPage').textContent = state.page + ' / ' + pages;
            $('rrScanPrev').disabled = state.page <= 1;
            $('rrScanNext').disabled = state.page >= pages;

            $('rrScanInfo').textContent = total ?
                'Menampilkan ' +
                (Math.min((state.page - 1) * state.pageSize + 1, total)) +
                '–' + Math.min(state.page * state.pageSize, total) +
                ' dari ' + total + ' hasil' :
                'Tidak ada hasil scan.';
        }

        function render() {
            const body = $('rrScanBody');
            const start = (state.page - 1) * state.pageSize;
            const rows = state.filtered.slice(start, start + state.pageSize);

            if (!rows.length) {
                body.innerHTML =
                    '<tr><td colspan="7" class="text-center py-4 text-muted">' +
                    (state.scanned ?
                        'Tidak ada hasil yang sesuai.' :
                        'Klik Mulai Scan untuk menemukan endpoint.') +
                    '</td></tr>';

                updatePagination();
                updateSummary();
                updateApplyButton();
                return;
            }

            body.innerHTML = rows.map((row) => {
                const id = rowId(row);
                const status = scanStatus(row);
                const checked = state.selected.has(id);

                return `
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="form-check-input rr-scan-select"
                            data-id="${escapeHtml(id)}"
                            aria-label="Pilih endpoint"
                            ${checked ? 'checked' : ''}
                        >
                    </td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(endpointName(row))}</div>
                        <small class="text-muted">ID: ${escapeHtml(id || '—')}</small>
                    </td>
                    <td>${escapeHtml(moduleName(row))}</td>
                    <td><code>${escapeHtml(handlerName(row))}</code></td>
                    <td>${statusBadge(status)}</td>
                    <td class="text-center">${enabled(row.is_routable) ? 'Ya' : 'Tidak'}</td>
                    <td class="text-center">${enabled(row.is_allowed) ? 'Ya' : 'Tidak'}</td>
                </tr>
            `;
            }).join('');

            updatePagination();
            updateSummary();
            updateApplyButton();
            updateSelectAll();
        }

        function filterRows() {
            const keyword = state.search.trim().toLowerCase();

            state.filtered = !keyword ? [...state.rows] :
                state.rows.filter((row) => [
                    endpointName(row),
                    moduleName(row),
                    handlerName(row),
                    scanStatus(row)
                ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));

            state.page = 1;
            render();
        }

        function updateApplyButton() {
            $('rrScanApply').disabled = state.busy || state.selected.size === 0;
            $('rrScanApply').textContent = state.selected.size ?
                'Apply Terpilih (' + state.selected.size + ')' :
                'Apply Terpilih';
        }

        function updateSelectAll() {
            const visible = state.filtered.slice(
                (state.page - 1) * state.pageSize,
                state.page * state.pageSize
            );

            const visibleIds = visible.map(rowId).filter(Boolean);
            const selectedVisible = visibleIds.filter((id) => state.selected.has(id)).length;

            $('rrScanCheckAll').checked =
                visibleIds.length > 0 && selectedVisible === visibleIds.length;

            $('rrScanCheckAll').indeterminate =
                selectedVisible > 0 && selectedVisible < visibleIds.length;
        }

        async function runScan(button) {
            if (state.busy) {
                return;
            }

            setBusy(true, button, 'Memindai...');

            $('rrScanBody').innerHTML =
                '<tr><td colspan="7" class="text-center py-4">' +
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Sedang memindai endpoint...' +
                '</td></tr>';

            try {
                const result = await request(api.scan, {
                    method: 'POST',
                    body: {}
                });

                state.rows = extractRows(result);
                state.selected.clear();
                state.scanned = true;

                filterRows();

                showMessage(
                    result.message || 'Scan selesai. Periksa hasil sebelum Apply.',
                    'success'
                );
            } catch (error) {
                $('rrScanBody').innerHTML =
                    '<tr><td colspan="7" class="text-center py-4 text-danger">' +
                    escapeHtml(error.message) + '</td></tr>';

                showMessage(
                    'Scan gagal. Pastikan endpoint POST /scan tersedia pada ApiRoutes.php. ' +
                    error.message,
                    'danger'
                );
            } finally {
                setBusy(false, button);
                updateApplyButton();
            }
        }

        async function applySelected(button) {
            if (state.busy || state.selected.size === 0) {
                return;
            }

            const selectedRows = state.rows.filter((row) =>
                state.selected.has(rowId(row))
            );

            // Kelompokkan berdasarkan perubahan hasil scan.
            const groups = {
                insert: [],
                update: []
            };

            selectedRows.forEach((row) => {
                const status = scanStatus(row);
                const endpoint = row.endpoint;

                if (typeof endpoint !== 'string' || !endpoint.trim()) {
                    return;
                }

                if (status === 'new' || status === 'discovered') {
                    groups.insert.push(endpoint);
                } else if (status === 'changed') {
                    groups.update.push(endpoint);
                }
            });

            const total = groups.insert.length + groups.update.length;

            if (total === 0) {
                showMessage(
                    'Tidak ada endpoint baru atau berubah yang dapat diterapkan. Periksa status hasil scan.',
                    'warning'
                );
                return;
            }

            if (!window.confirm(
                    `Terapkan ${total} endpoint terpilih ke Route Registry?`
                )) {
                return;
            }

            setBusy(true, button, 'Menerapkan...');

            try {
                const actions = [{
                        action: 'insert',
                        selected: groups.insert
                    },
                    {
                        action: 'update',
                        selected: groups.update
                    }
                ];

                let processed = 0;

                for (const item of actions) {
                    if (item.selected.length === 0) {
                        continue;
                    }

                    const result = await request(api.apply, {
                        method: 'POST',
                        body: {
                            action: item.action,
                            selected: item.selected
                        }
                    });

                    if (result.status !== 'success') {
                        throw new Error(
                            result.message || `Apply ${item.action} gagal.`
                        );
                    }

                    processed += item.selected.length;
                }

                state.selected.clear();

                showMessage(
                    `${processed} endpoint berhasil diproses.`,
                    'success'
                );

                await runScanWithoutButton();

            } catch (error) {
                showMessage(
                    'Apply gagal: ' + error.message,
                    'danger'
                );
            } finally {
                setBusy(false, button);
                updateApplyButton();
            }
        }

        async function runScanWithoutButton() {
            const result = await request(api.scan, {
                method: 'POST',
                body: {}
            });

            state.rows = extractRows(result);
            state.scanned = true;

            filterRows();
        }

        $('rrScanStart').addEventListener('click', function() {
            runScan(this);
        });

        $('rrScanSearch').addEventListener('input', function() {
            state.search = this.value;
            filterRows();
        });

        $('rrScanBody').addEventListener('change', function(event) {
            const checkbox = event.target.closest('.rr-scan-select');

            if (!checkbox) {
                return;
            }

            const id = checkbox.dataset.id;

            if (checkbox.checked) {
                state.selected.add(id);
            } else {
                state.selected.delete(id);
            }

            updateApplyButton();
            updateSelectAll();
        });

        $('rrScanCheckAll').addEventListener('change', function() {
            const visible = state.filtered.slice(
                (state.page - 1) * state.pageSize,
                state.page * state.pageSize
            );

            visible.forEach((row) => {
                const id = rowId(row);

                if (!id) {
                    return;
                }

                if (this.checked) {
                    state.selected.add(id);
                } else {
                    state.selected.delete(id);
                }
            });

            render();
        });

        $('rrScanSelectAll').addEventListener('click', function() {
            const visible = state.filtered.slice(
                (state.page - 1) * state.pageSize,
                state.page * state.pageSize
            );

            const visibleIds = visible.map(rowId).filter(Boolean);
            const allSelected = visibleIds.length > 0 &&
                visibleIds.every((id) => state.selected.has(id));

            visibleIds.forEach((id) => {
                if (allSelected) {
                    state.selected.delete(id);
                } else {
                    state.selected.add(id);
                }
            });

            render();
        });

        $('rrScanApply').addEventListener('click', function() {
            applySelected(this);
        });

        $('rrScanPrev').addEventListener('click', function() {
            if (state.page > 1) {
                state.page--;
                render();
            }
        });

        $('rrScanNext').addEventListener('click', function() {
            const pages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));

            if (state.page < pages) {
                state.page++;
                render();
            }
        });
    })();
</script>
<?php $this->endSection() ?>
