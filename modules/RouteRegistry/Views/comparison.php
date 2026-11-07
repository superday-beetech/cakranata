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
                value === '1' ||
                value === 'true';
        }

        function statusBadge(enabled, yes = 'Aktif', no = 'Nonaktif') {
            return enabled ?
                '<span class="badge bg-success-subtle text-success">' + yes + '</span>' :
                '<span class="badge bg-secondary-subtle text-secondary">' + no + '</span>';
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
