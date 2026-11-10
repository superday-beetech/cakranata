<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Route Registry</h4>
        <button type="button" id="btnScan" class="btn btn-primary">
            Scan Endpoint
        </button>

        <div class="d-flex gap-2">
            <button type="button" id="btnSyncPermission"
                class="btn btn-outline-primary" disabled>
                <i class="fa fa-shield-alt"></i>
                Sync Permission
            </button>

            <button type="button" id="btnSyncNavigation"
                class="btn btn-outline-success" disabled>
                <i class="fa fa-sitemap"></i>
                Sync Menu Navigation
            </button>
        </div>
    </div>

    <!-- <div class="alert alert-info">
        Scan hanya membaca struktur module. Endpoint baru tidak otomatis
        diizinkan. HTTP Verb tidak mengubah status Routable maupun Allowed.
    </div> -->

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select id="dataSource" class="form-select">
                <option value="registry">Registry Terdaftar</option>
                <option value="unregistered">Registry Belum Terdaftar</option>
                <option value="scan">Hasil Scan</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="search" id="searchBox" class="form-control"
                placeholder="Cari module, controller, method, endpoint...">
        </div>
        <div class="col-md-4">
            <select id="perPage" class="form-select">
                <option value="10">10 per halaman</option>
                <option value="25" selected>25 per halaman</option>
                <option value="50">50 per halaman</option>
                <option value="100">100 per halaman</option>
            </select>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button id="btnInsert" class="btn btn-success">Insert Terpilih</button>
        <button id="btnUpdate" class="btn btn-warning">
            Update Metadata Terdaftar
        </button>
        <button id="btnDelete" class="btn btn-danger">Delete Terpilih</button>
    </div>

    <div class="table-responsive border rounded-4">
        <table class="table table-bordered align-middle text-nowrap table-sm">
            <thead>
                <tr>
                    <th scope="col" class="text-center">
                        <input type="checkbox" id="selectAll" class="form-check-input"
                            aria-label="Pilih endpoint pada halaman ini">
                    </th>
                    <th scope="col" class="text-center">Module</th>
                    <!-- <th scope="col" class="text-center">Controller</th>
                    <th scope="col" class="text-center">Method</th> -->
                    <th scope="col" class="text-center">Endpoint / Permission / URL</th>
                    <th scope="col" class="text-center">Registry</th>
                    <th scope="col" class="text-center">HTTP Verb</th>
                    <th scope="col" class="text-center">Routable</th>
                    <th scope="col" class="text-center">Allowed</th>
                </tr>
            </thead>
            <tbody id="registryBody">
                <tr>
                    <td colspan="9" scope="row" class="text-center text-muted">
                        Memuat data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small id="paginationInfo" class="text-muted"></small>
        <nav aria-label="Navigasi halaman registry">
            <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
        </nav>
    </div>

    <div id="registryMessage" class="mt-3" aria-live="polite"></div>
</div>

<div class="modal fade" id="verbModal" tabindex="-1"
    aria-labelledby="verbModalTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verbModalTitle">
                    Pengaturan HTTP Verb
                </h5>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <label for="verbEndpoint" class="form-label">Endpoint</label>
                <input type="text" id="verbEndpoint"
                    class="form-control mb-3" readonly>

                <div class="alert alert-warning py-2">
                    Pengaturan ini tidak otomatis mengaktifkan akses endpoint.
                </div>

                <div class="row g-2">
                    <?php foreach (
                        $verbs
                        as $verb
                    ): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input verb-check"
                                    type="checkbox"
                                    value="<?= esc($verb) ?>"
                                    id="verb<?= esc($verb) ?>">
                                <label class="form-check-label"
                                    for="verb<?= esc($verb) ?>">
                                    <?= esc($verb) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveVerbs">
                    Simpan Verb
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="syncPreviewModal" tabindex="-1"
    aria-labelledby="syncPreviewModalTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="syncPreviewModalTitle">
                    Hasil Sync
                </h5>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead id="syncPreviewHead"></thead>
                        <tbody id="syncPreviewBody"></tbody>
                    </table>
                </div>

                <div id="syncPreviewInfo"
                    class="text-muted small mt-2"></div>
            </div>

            <div class="modal-footer">
                <button type="button"
                    class="btn btn-primary d-none"
                    id="btnApplySync">
                    <i class="fa fa-sync"></i>
                    <span id="btnApplySyncText">Terapkan Pilihan</span>
                </button>
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">
                    Tutup
                </button>
            </div>

        </div>
    </div>
</div>
<?= $this->section('cssHTML') ?>
<style>
    .verb-grid-responsive {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .verb-grid-responsive .form-check {
        margin-bottom: 0;
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('jsFooterInline') ?>
<script>
    window.routeRegistryConfig = {
        scanUrl: <?= json_encode(site_url('api/v1/' . $seg . '/scan')) ?>,
        applyUrl: <?= json_encode(site_url('api/v1/' . $seg . '/apply')) ?>,
        verbsUrl: <?= json_encode(site_url('api/v1/' . $seg . '/getVerbs')) ?>,
        saveVerbsUrl: <?= json_encode(site_url('api/v1/' . $seg . '/saveVerbs')) ?>,
        registeredUrl: <?= json_encode(site_url('api/v1/' . $seg . '/registered')) ?>,
        statusUrl: <?= json_encode(site_url('api/v1/' . $seg . '/update-status')) ?>,
        syncPermissionsUrl: <?= json_encode(site_url('api/v1/' . $seg . '/sync-permissions')) ?>,
        syncNavigationUrl: <?= json_encode(site_url('api/v1/' . $seg . '/sync-navigation')) ?>,
        authPermissionUrl: <?= json_encode(site_url('api/v1/' . $seg . '/auth-sync-permissions')) ?>
    };


    (() => {
        'use strict';

        const config = window.routeRegistryConfig;

        async function request(url, options = {}) {
            const headers = new Headers(options.headers || {});
            headers.set('Accept', 'application/json');

            if (options.body) {
                headers.set('Content-Type', 'application/json');
            }

            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });

            const type = response.headers.get('content-type') || '';

            if (!type.includes('application/json')) {
                throw new Error(`Respons bukan JSON (HTTP ${response.status}).`);
            }

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Permintaan gagal.');
            }

            return result;
        }

        function showMessage(text, type = 'info') {
            const el = document.getElementById('registryMessage');
            const alert = document.createElement('div');

            alert.className = `alert alert-${type}`;
            alert.setAttribute('role', 'alert');
            alert.textContent = text;

            el.replaceChildren(alert);
        }

        function escape(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        window.RouteRegistry = {
            config,
            request,
            showMessage,
            escape,
            state: {
                source: 'registry',
                page: 1,
                perPage: 25,
                search: '',
                rows: [],
                selected: new Set(),
                pagination: {
                    page: 1,
                    perPage: 25,
                    total: 0,
                    totalPages: 1,
                    hasPrev: false,
                    hasNext: false
                },
                busy: false,
                activeVerbId: null
            }
        };
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;
        const {
            escape
        } = app;
        const body = document.getElementById('registryBody');
        const selectAll = document.getElementById('selectAll');
        // const verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        const verbs = <?php echo json_encode($verbs); ?>;

        function statusButton(row, field, label) {
            const registry = row.registry;

            if (!registry?.id) return '<span class="text-muted">—</span>';

            const key = field === 'routable' ? 'is_routable' : 'is_allowed';
            const enabled = Number(registry[key]) === 1;

            return `
            <button type="button"
                class="btn p-0 border-0 btn-toggle-status"
                data-id="${escape(registry.id)}"
                data-field="${field}"
                data-value="${!enabled}"
                aria-pressed="${enabled}">
                ${enabled ? '<i class="bi bi-check-square-fill text-success fs-4"></i>' : '<i class="bi bi-square fs-4"></i>'}
            </button>`;
        }

        function render() {
            const {
                rows,
                selected
            } = app.state;

            if (!rows.length) {
                body.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-muted">
                    Tidak ada endpoint pada halaman ini.
                </td>
            </tr>`;
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            let previousGroup = '';
            const html = [];

            for (const row of rows) {
                const group = `${row.module}::${row.controller}`;

                // Render header grup jika berubah
                if (group !== previousGroup) {
                    html.push(`
                <tr class="table-secondary">
                    <th colspan="9" scope="row">
                        ${escape(row.module)} / ${escape(row.controller)}
                    </th>
                </tr>`);
                    previousGroup = group;
                }

                const registry = row.registry || {};
                const registered = Boolean(registry.id);
                const checked = selected.has(row.endpoint);
                const currentVerbs = Array.isArray(registry.verbs) ?
                    registry.verbs.map(v => String(v).toUpperCase()) : [];

                // Template HTML untuk HTTP Verbs
                const verbHtml = registered ? `
            <div class="verb-grid-responsive">
                ${verbs.map(verb => `
                    <label class="form-check mb-0">
                        <input type="checkbox"
                            class="form-check-input verb-inline-check"
                            data-id="${escape(registry.id)}"
                            data-endpoint="${escape(row.endpoint)}"
                            value="${verb}"
                            ${currentVerbs.includes(verb) ? 'checked' : ''}>
                        <span class="form-check-label">${verb}</span>
                    </label>
                `).join('')}
            </div>
            <div class="d-grid my-1">
                <button type="button"
                    class="btn btn-sm bg-info-subtle text-info btn-manage-verbs"
                    data-id="${escape(registry.id)}"
                    data-endpoint="${escape(row.endpoint)}">
                    Atur Verb
                </button>
            </div>` : `
            <span class="text-muted fs-4 text-center">
                <i class="bi bi-question-circle"></i>
            </span>`;

                // Render baris data utama
                html.push(`
            <tr>
                <td scope="row">
                    <input type="checkbox" class="row-check form-check-input"
                        value="${escape(row.endpoint)}"
                        ${checked ? 'checked' : ''}>
                </td>
                <td class="p-0">
                    <ul class="list-group list-group-flush border-0 p-0">
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi-collection fs-5 me-2 text-primary"></i>
                            ${escape(row.module)}
                        </li>
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi-cpu fs-5 me-2 text-primary"></i>
                            ${escape(row.controller)}
                        </li>
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi-braces fs-5 me-2 text-primary"></i>
                            ${escape(row.method)}
                        </li>
                    </ul>
                </td>
                <td class="p-0">
                    <ul class="list-group list-group-flush border-0 p-0">
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi-diagram-3 fs-5 me-2 text-primary"></i>
                            ${escape(row.endpoint)}
                        </li>
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi-shield-check fs-5 me-2 text-primary"></i>
                            ${escape(registry.permissions)}
                        </li>
                        ${registry.url ? `
                        <li class="list-group-item border-0 py-1 px-2 d-flex align-items-center bg-transparent">
                            <i class="bi bi-link-45deg fs-5 me-2 text-primary"></i>
                            ${escape(registry.url)}
                        </li>` : ''}
                    </ul>
                </td>
                <td class="text-center">
                    ${registered
                        ? '<i class="bi bi-check-square-fill text-success"></i>'
                        : '<i class="bi bi-x-circle-fill text-danger"></i>'}
                </td>
                <td>${verbHtml}</td>
                <td class="text-center">${statusButton(row, 'routable', 'Routable')}</td>
                <td class="text-center">${statusButton(row, 'allowed', 'Allowed')}</td>
            </tr>`);
            }

            body.innerHTML = html.join('');
            updateSelectAll();
        }

        function updateSelectAll() {
            const checks = [...body.querySelectorAll('.row-check')];
            const count = checks.filter(el => el.checked).length;

            selectAll.checked = checks.length > 0 && count === checks.length;
            selectAll.indeterminate = count > 0 && count < checks.length;
        }

        body.addEventListener('change', event => {
            if (!event.target.matches('.row-check')) return;

            const endpoint = event.target.value;

            if (event.target.checked) app.state.selected.add(endpoint);
            else app.state.selected.delete(endpoint);

            updateSelectAll();
        });

        selectAll.addEventListener('change', () => {
            body.querySelectorAll('.row-check').forEach(input => {
                if (selectAll.checked) app.state.selected.add(input.value);
                else app.state.selected.delete(input.value);

                input.checked = selectAll.checked;
            });

            updateSelectAll();
        });

        app.table = {
            render,
            updateSelectAll
        };
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;
        const pagination = document.getElementById('pagination');
        const info = document.getElementById('paginationInfo');

        function render() {
            const p = app.state.pagination;

            info.textContent = `Total ${p.total} endpoint — halaman ${p.page} dari ${p.totalPages}`;

            const button = (label, page, disabled, active = false) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <button class="page-link" type="button"
                    data-page="${page}" ${disabled ? 'disabled' : ''}>
                    ${label}
                </button>
            </li>`;

            const items = [];
            items.push(button('«', 1, p.page <= 1));
            items.push(button('‹', p.page - 1, !p.hasPrev));

            const start = Math.max(1, p.page - 2);
            const end = Math.min(p.totalPages, p.page + 2);

            for (let page = start; page <= end; page++) {
                items.push(button(page, page, false, page === p.page));
            }

            items.push(button('›', p.page + 1, !p.hasNext));
            items.push(button('»', p.totalPages, !p.hasNext));

            pagination.innerHTML = items.join('');
        }

        pagination.addEventListener('click', event => {
            const button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;

            app.state.page = Number(button.dataset.page);
            app.load();
        });

        app.pagination = {
            render
        };
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;
        const body = document.getElementById('registryBody');

        body.addEventListener('click', async event => {
            const button = event.target.closest('.btn-toggle-status');
            if (!button || app.state.busy) return;

            const id = Number(button.dataset.id);
            const field = button.dataset.field;
            const value = button.dataset.value === 'true';

            if (!Number.isSafeInteger(id) || id < 1 ||
                !['routable', 'allowed'].includes(field)) {
                app.showMessage('Data status tidak valid.', 'danger');
                return;
            }

            button.disabled = true;

            try {
                const result = await app.request(app.config.statusUrl, {
                    method: 'PATCH',
                    body: JSON.stringify({
                        id,
                        field,
                        value
                    })
                });

                const data = result.data;
                if (Number(data.id) !== id) {
                    throw new Error('Respons status tidak sesuai.');
                }

                const row = app.state.rows.find(
                    item => Number(item.registry?.id) === id
                );

                if (row?.registry) {
                    row.registry.is_routable = Number(data.is_routable);
                    row.registry.is_allowed = Number(data.is_allowed);
                }

                app.table.render();
                app.showMessage(result.message, 'success');
            } catch (error) {
                app.showMessage(error.message, 'danger');
            } finally {
                button.disabled = false;
            }
        });
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;
        const supported = <?php echo json_encode($verbs); ?>;;
        const modalElement = document.getElementById('verbModal');
        const modal = new bootstrap.Modal(modalElement);
        const endpointInput = document.getElementById('verbEndpoint');
        const saveButton = document.getElementById('btnSaveVerbs');
        const body = document.getElementById('registryBody');

        async function openModal(id, endpoint) {
            app.state.activeVerbId = Number(id);
            endpointInput.value = endpoint;
            saveButton.disabled = true;

            document.querySelectorAll('.verb-check').forEach(input => {
                input.checked = false;
            });

            modal.show();

            try {
                const result = await app.request(
                    `${app.config.verbsUrl}/${encodeURIComponent(id)}`
                );

                endpointInput.value = result.data.endpoint || endpoint;
                const current = result.data.verbs || [];

                document.querySelectorAll('.verb-check').forEach(input => {
                    input.checked = current.includes(input.value);
                });
            } catch (error) {
                modal.hide();
                app.showMessage(error.message, 'danger');
            } finally {
                saveButton.disabled = false;
            }
        }

        async function save(endpoint, verbs) {
            return app.request(app.config.saveVerbsUrl, {
                method: 'POST',
                body: JSON.stringify({
                    endpoint,
                    verbs
                })
            });
        }

        saveButton.addEventListener('click', async () => {
            if (!app.state.activeVerbId) return;

            const endpoint = endpointInput.value.trim();
            const verbs = [...document.querySelectorAll('.verb-check:checked')]
                .map(input => input.value)
                .filter(verb => supported.includes(verb));

            saveButton.disabled = true;

            try {
                const result = await save(endpoint, verbs);

                const row = app.state.rows.find(item => item.endpoint === endpoint);
                if (row?.registry) row.registry.verbs = verbs;

                modal.hide();
                app.table.render();
                app.showMessage(result.message, 'success');
            } catch (error) {
                app.showMessage(error.message, 'danger');
            } finally {
                saveButton.disabled = false;
            }
        });

        body.addEventListener('click', event => {
            const button = event.target.closest('.btn-manage-verbs');
            if (button) openModal(button.dataset.id, button.dataset.endpoint);
        });

        body.addEventListener('change', async event => {
            const input = event.target;
            if (!input.matches('.verb-inline-check')) return;

            const endpoint = input.dataset.endpoint;
            const row = app.state.rows.find(item => item.endpoint === endpoint);
            if (!row?.registry) return;

            const previous = [...(row.registry.verbs || [])];
            const next = input.checked ? [...new Set([...previous, input.value])] :
                previous.filter(verb => verb !== input.value);

            input.disabled = true;

            try {
                await save(endpoint, next);
                row.registry.verbs = next;
                app.showMessage('HTTP Verb berhasil diperbarui.', 'success');
            } catch (error) {
                input.checked = previous.includes(input.value);
                app.showMessage(error.message, 'danger');
            } finally {
                input.disabled = false;
            }
        });
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;

        function setBusy(busy) {
            app.state.busy = busy;

            ['btnScan', 'btnInsert', 'btnUpdate', 'btnDelete'].forEach(id => {
                document.getElementById(id).disabled = busy;
            });
        }

        async function scan() {
            if (app.state.busy) return;

            setBusy(true);

            try {
                const result = await app.request(app.config.scanUrl);

                app.state.rows = result.data?.items || [];
                app.state.scanRows = result.data?.items || [];
                app.state.source = 'scan';
                app.state.page = 1;
                document.getElementById('dataSource').value = 'scan';

                app.table.render();
                app.showMessage(
                    `Scan selesai: ${app.state.rows.length} endpoint. Database tidak diubah.`,
                    'success'
                );
                document.getElementById('btnSyncPermission').disabled = false;
                document.getElementById('btnSyncNavigation').disabled = false;
            } catch (error) {
                app.showMessage(error.message, 'danger');
            } finally {
                setBusy(false);
            }
        }

        async function apply(action) {
            if (app.state.busy) return;

            let selected;

            if (action === 'update') {
                selected = app.state.rows
                    .filter(row => row.registry?.id)
                    .map(row => row.endpoint);
            } else {
                selected = [...app.state.selected];
            }

            if (!selected.length) {
                app.showMessage('Pilih minimal satu endpoint.', 'warning');
                return;
            }

            if (!confirm(`${action.toUpperCase()} ${selected.length} endpoint?`)) {
                return;
            }

            setBusy(true);

            try {
                const result = await app.request(app.config.applyUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        action,
                        selected
                    })
                });

                app.state.selected.clear();
                app.showMessage(result.message, 'success');

                // Muat ulang data setelah aksi berhasil.
                if (app.state.source === 'registry') {
                    app.state.page = 1;
                    await app.load();
                } else {
                    setBusy(false);
                    await scan();
                }
            } catch (error) {
                app.showMessage(error.message, 'danger');
            } finally {
                setBusy(false);
            }
        }

        document.getElementById('btnScan').addEventListener('click', scan);
        document.getElementById('btnInsert').addEventListener('click', () => apply('insert'));
        document.getElementById('btnUpdate').addEventListener('click', () => apply('update'));
        document.getElementById('btnDelete').addEventListener('click', () => apply('delete'));

        app.actions = {
            scan,
            apply
        };
    })();


    (() => {
        'use strict';

        const app = window.RouteRegistry;
        const search = document.getElementById('searchBox');
        const perPage = document.getElementById('perPage');
        const source = document.getElementById('dataSource');

        const syncPreviewModalElement = document.getElementById('syncPreviewModal');
        const syncPreviewModalTitle = document.getElementById('syncPreviewModalTitle');
        const syncPreviewHead = document.getElementById('syncPreviewHead');
        const syncPreviewBody = document.getElementById('syncPreviewBody');
        const syncPreviewInfo = document.getElementById('syncPreviewInfo');

        const syncPreviewModal = bootstrap.Modal.getOrCreateInstance(syncPreviewModalElement);

        function toKebabCase(value) {
            return value.replace(/Controller$/i, '').replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
        }

        function buildMenuUrl(row) {
            const toSlug = value => value.trim().replace(/Controller$/i, '').replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
            const module = toSlug(row.module || '');
            const controller = toSlug(row.controller || '');
            const method = (row.method || '').trim().toLowerCase();

            if (!module || !controller) {
                return null;
            }

            // Jika controller sama dengan module, gunakan method sebagai subpath
            if (module === controller) {
                return method === 'index' ? module : `${module}/${method}`;
            }

            return `${module}/${controller}`;
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }

        function renderSyncPreview(title, columns, rows, type) {
            const btnApplySync = document.getElementById('btnApplySync');
            const btnApplySyncText = document.getElementById('btnApplySyncText');

            const syncButtonConfig = {
                permission: 'Terapkan Permission Terpilih',
                navigation: 'Terapkan Menu Terpilih'
            };

            const buttonText = syncButtonConfig[type];
            btnApplySync?.classList.toggle('d-none', !buttonText);

            if (btnApplySyncText && buttonText) {
                btnApplySyncText.textContent = buttonText;
            }

            syncPreviewModalTitle.textContent = title;
            const selectAllId = 'syncPreviewSelectAll';
            syncPreviewHead.innerHTML = `
        <tr>
            <th class="text-center" style="width: 45px">
                <input type="checkbox" id="${selectAllId}"
                    class="form-check-input" title="Pilih Semua">
            </th>
            <th>No.</th>
            ${columns.map(column =>
                `<th>${escapeHtml(column.label)}</th>`
            ).join('')}
        </tr>
    `;

            syncPreviewBody.innerHTML = rows.length ?
                rows.map((row, index) => `
            <tr>
                <td class="text-center">
                    <input type="checkbox"
                        class="form-check-input sync-row-check"
                        data-index="${index}">
                </td>
                <td>${index + 1}</td>
                ${columns.map(column => `
                    <td>${escapeHtml(column.value(row, index))}</td>
                `).join('')}
            </tr>
        `).join('') :
                `
            <tr><td colspan="${columns.length + 2}" class="text-center text-muted">Tidak ada data.</td></tr>`;

            // Simpan data dan tipe preview yang sedang ditampilkan.
            app.state.syncPreview = {
                type,
                rows,
                selected: new Set()
            };

            const btnApplyPermissionSync = document.getElementById('btnApplyPermissionSync');
            const btnApplyNavigationSync = document.getElementById('btnApplyNavigationSync');

            btnApplyPermissionSync?.classList.toggle('d-none', type !== 'permission');
            btnApplyNavigationSync?.classList.toggle('d-none', type !== 'navigation');

            function updateSelection() {
                const state = app.state.syncPreview;
                const checkboxes = [...syncPreviewBody.querySelectorAll('.sync-row-check')];

                state.selected = new Set(checkboxes.filter(checkbox => checkbox.checked).map(checkbox => Number(checkbox.dataset.index)));

                const selectAll = document.getElementById(selectAllId);

                if (selectAll) {
                    selectAll.checked = checkboxes.length > 0 && checkboxes.every(checkbox => checkbox.checked);
                    selectAll.indeterminate = checkboxes.some(checkbox => checkbox.checked) && !selectAll.checked;
                }

                syncPreviewInfo.textContent = `Total: ${rows.length} data | Terpilih: ${state.selected.size}`;
            }
            const selectAll = document.getElementById(selectAllId);

            selectAll.addEventListener('change', () => {
                syncPreviewBody.querySelectorAll('.sync-row-check').forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });

                updateSelection();
            });

            syncPreviewBody
                .querySelectorAll('.sync-row-check').forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelection);
                });
            updateSelection();
            syncPreviewModal.show();
        }
        let searchTimer;
        app.load = async function() {
            if (app.state.busy) return;

            app.state.busy = true;

            try {
                const params = new URLSearchParams({
                    page: app.state.page,
                    perPage: app.state.perPage,
                    search: app.state.search,
                    source: app.state.source,
                });

                const result = await app.request(
                    `${app.config.registeredUrl}?${params.toString()}`
                );

                app.state.rows = result.data?.items || [];
                app.state.pagination = result.data?.pagination || {
                    page: 1,
                    perPage: app.state.perPage,
                    total: app.state.rows.length,
                    totalPages: 1,
                    hasPrev: false,
                    hasNext: false
                };

                app.state.source = 'registry';
                app.table.render();
                app.pagination.render();
            } catch (error) {
                app.showMessage(error.message, 'danger');
            } finally {
                app.state.busy = false;
            }
        };

        search.addEventListener('input', () => {
            clearTimeout(searchTimer);

            searchTimer = setTimeout(() => {
                app.state.search = search.value.trim();
                app.state.page = 1;

                if (app.state.source === 'registry') {
                    app.load();
                } else {
                    const q = app.state.search.toLowerCase();
                    const filtered = app.state.rows.filter(row => [row.module, row.controller, row.method, row.endpoint]
                        .some(value => String(value ?? '').toLowerCase().includes(q))
                    );

                    const original = app.state.rows;
                    app.state.rows = filtered;
                    app.table.render();
                    app.state.rows = original;
                }
            }, 300);
        });

        source.addEventListener('change', async () => {
            app.state.source = source.value;
            app.state.page = 1;

            if (app.state.source === 'scan') {
                await app.actions.scan();
            } else if (app.state.source === 'unregistered') {
                await app.actions.scan();
                source.value = 'unregistered';
                app.state.source = 'unregistered';
                app.state.rows = app.state.rows.filter(row => row.state === 'new');
                app.table.render();
            } else {
                await app.load();
            }
        });

        document.getElementById('btnApplySync')
            ?.addEventListener('click', async function() {
                const preview = app.state.syncPreview;
                if (!preview) {
                    app.showMessage('Preview sinkronisasi tidak tersedia.', 'warning');
                    return;
                }

                const button = this;
                const originalHtml = button.innerHTML;

                button.disabled = true;
                button.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
                try {
                    switch (preview.type) {
                        case 'permission':
                            await applyPermissionSync();
                            break;

                        case 'navigation':
                            await applyNavigationSync();
                            break;

                        default:
                            throw new Error('Jenis sinkronisasi tidak dikenal.');
                    }
                } catch (error) {
                    console.error('Sync error:', error);

                    app.showMessage(error.message || 'Sinkronisasi gagal.', 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            });

        function resetSyncSelection(state) {
            state.selected.clear();

            document.querySelectorAll('#syncPreviewBody .sync-row-check').forEach(checkbox => {
                checkbox.checked = false;
            });

            const selectAll = document.getElementById('syncPreviewSelectAll');

            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }

        function updateSyncPreviewInfo(message) {
            const info = document.getElementById('syncPreviewInfo');
            if (info) {
                info.textContent = message;
            }
        }
        document.getElementById('btnSyncPermission')
            ?.addEventListener('click', () => {
                const rows = (app.state.scanRows || []).filter(row => row.permission && row.endpoint).sort((a, b) =>
                    (a.module || '').localeCompare(b.module || '') ||
                    (a.controller || '').localeCompare(b.controller || '') ||
                    (a.method || '').localeCompare(b.method || '')
                );

                renderSyncPreview('Preview Permission',
                    [{
                        label: 'Module',
                        value: row => row.module
                    }, {
                        label: 'Controller',
                        value: row => row.controller
                    }, {
                        label: 'Method',
                        value: row => row.method
                    }, {
                        label: 'Endpoint',
                        value: row => row.endpoint
                    }, {
                        label: 'Permission',
                        value: row => row.permission
                    }], rows, 'permission');
            });


        async function applyPermissionSync() {
            const state = app.state.syncPreview;

            if (!state || state.type !== 'permission') {
                throw new Error('Preview permission tidak tersedia.');
            }

            const items = [...state.selected].map(index => state.rows[index]).filter(row => row?.endpoint && row?.permission).map(row => ({
                endpoint: row.endpoint,
                permission: row.permission
            }));

            if (items.length === 0) {
                app.showMessage('Pilih minimal satu permission.', 'warning');
                return;
            }

            const result = await app.request(
                app.config.syncPermissionsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        items
                    })
                }
            );

            if (result.status !== 'success') {
                throw new Error(result.message || 'Sinkronisasi permission gagal.');
            }

            const data = result.data || {};
            updateSyncPreviewInfo(`Selesai — Dipilih: ${items.length} | ` + `Ditambahkan: ${data.inserted ?? 0} | ` + `Sudah ada: ${data.existing ?? 0} | ` + `Dilewati: ${data.skipped ?? 0}`);
            resetSyncSelection(state);
            app.showMessage(`Sinkronisasi permission berhasil: ` + `${data.inserted ?? 0} ditambahkan, ` + `${data.existing ?? 0} sudah ada, ` + `${data.skipped ?? 0} dilewati.`, 'success');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('syncPreviewModal')).hide();
        }

        document.getElementById('btnSyncNavigation')
            ?.addEventListener('click', () => {
                const rows = (app.state.scanRows || []).filter(row => row.has_interface === true && ['index', 'dashboard'].includes((row.method || '').toLowerCase()))
                    .map(row => ({
                        ...row,
                        url: buildMenuUrl(row)
                    }))
                    .filter(row => row.url !== null).sort((a, b) => (a.module || '').localeCompare(b.module || '') || (a.controller || '').localeCompare(b.controller || '') || (a.method || '').localeCompare(b.method || ''));

                renderSyncPreview(
                    'Preview Menu Navigation',
                    [{
                            label: 'Module',
                            value: row => row.module
                        },
                        {
                            label: 'Controller',
                            value: row => row.controller
                        },
                        {
                            label: 'Method',
                            value: row => row.method
                        },
                        {
                            label: 'URL Menu',
                            value: row => row.url
                        },
                        {
                            label: 'Permission',
                            value: row => row.permission
                        }
                    ], rows, 'navigation');
            });


        document.getElementById('btnSyncNavigation')
            ?.addEventListener('click', () => {
                const rows = (app.state.scanRows || []).filter(row => row.has_interface === true).map(row => ({
                    ...row,
                    url: buildMenuUrl(row)
                })).filter(row => row.url !== null).sort((a, b) => (a.module || '').localeCompare(b.module || '') || (a.controller || '').localeCompare(b.controller || '') || (a.method || '').localeCompare(b.method || ''));

                renderSyncPreview(
                    'Preview Menu Navigation',
                    [{
                            label: 'Module',
                            value: row => row.module
                        },
                        {
                            label: 'Controller',
                            value: row => row.controller
                        },
                        {
                            label: 'Method',
                            value: row => row.method
                        },
                        {
                            label: 'URL Menu',
                            value: row => row.url
                        },
                        {
                            label: 'Permission',
                            value: row => row.permission
                        }
                    ], rows, 'navigation');
            });

        async function applyNavigationSync() {
            const state = app.state.syncPreview;

            if (!state || state.type !== 'navigation') {
                throw new Error('Preview menu tidak tersedia.');
            }

            const items = [...state.selected].map(index => state.rows[index]).filter(row => row?.endpoint && row?.url).map(row => ({
                endpoint: row.endpoint,
                module: row.module,
                controller: row.controller,
                method: row.method,
                url: row.url
            }));

            if (items.length === 0) {
                app.showMessage('Pilih minimal satu menu untuk disinkronkan.', 'warning');
                return;
            }

            const response = await app.request(
                app.config.syncNavigationUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        items
                    })
                }
            );

            if (response.status !== 'success') {
                throw new Error(
                    response.message || 'Sinkronisasi menu gagal.'
                );
            }

            const data = response.data || {};

            updateSyncPreviewInfo(`Selesai — Dipilih: ${items.length} | ` + `Ditambahkan: ${data.inserted ?? 0} | ` +
                `Diperbarui: ${data.updated ?? 0} | ` +
                `Sudah ada: ${data.existing ?? 0} | ` +
                `Dilewati: ${data.skipped ?? 0}`
            );

            resetSyncSelection(state);

            app.showMessage(
                `Sinkronisasi menu berhasil: ` +
                `${data.inserted ?? 0} ditambahkan, ` +
                `${data.updated ?? 0} diperbarui, ` +
                `${data.existing ?? 0} sudah ada, ` +
                `${data.skipped ?? 0} dilewati.`,
                'success'
            );

            bootstrap.Modal.getOrCreateInstance(document.getElementById('syncPreviewModal')).hide();
        }

        app.load();
    })();
</script>
<!-- <script src="</?= base_url('assets/js/route-registry/api.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/table.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/pagination.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/status.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/verbs.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/actions.js') ?>"></script>
<script src="</?= base_url('assets/js/route-registry/index.js') ?>"></script> -->
<?= $this->endSection() ?>
