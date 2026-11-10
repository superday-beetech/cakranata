<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Registry</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Route Registry</h4>

            <button type="button" id="btnScan" class="btn btn-primary">
                Scan Endpoint
            </button>
        </div>

        <div class="alert alert-info">
            Scan hanya membaca struktur module. Endpoint baru tidak otomatis
            diizinkan. Status akses dan HTTP verb tidak diubah oleh scan.
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <input
                    type="search"
                    id="searchBox"
                    class="form-control"
                    placeholder="Cari module, controller, atau method...">
            </div>

            <div class="col-md-6 text-md-end">
                <button id="btnInsert" class="btn btn-success">
                    Insert Terpilih
                </button>

                <button id="btnUpdate" class="btn btn-warning">
                    Update Terpilih
                </button>

                <button id="btnDelete" class="btn btn-danger">
                    Delete Terpilih
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                id="selectAll"
                                aria-label="Pilih semua endpoint">
                        </th>
                        <th>Module</th>
                        <th>Controller</th>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Registry</th>
                        <th>Routable</th>
                        <th>Allowed</th>
                    </tr>
                </thead>

                <tbody id="registryBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            Klik Scan Endpoint untuk memuat data.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="registryMessage" class="mt-3" aria-live="polite"></div>
    </div>

    <script>
        (() => {
            'use strict';

            /*
             * Sesuaikan dengan base URL aplikasi CI4.
             *
             * Contoh:
             * http://localhost/cakra/
             *
             * Pastikan akhiran slash tersedia.
             */
            /* const baseURL = '<?= site_url(); ?>';

            const scanUrl = `${baseURL}routeregistry/route/api/scan`;
            const applyUrl = `${baseURL}routeregistry/route/api/apply`;
 */
            const scanUrl = <?= json_encode(
                                site_url('routeregistry/route/api/scan')
                            ) ?>;

            const applyUrl = <?= json_encode(
                                    site_url('routeregistry/route/api/apply')
                                ) ?>;
            const body = document.getElementById('registryBody');
            const message = document.getElementById('registryMessage');
            const searchBox = document.getElementById('searchBox');
            const selectAll = document.getElementById('selectAll');

            let rows = [];
            let busy = false;

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, char => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[char]);
            }

            function showMessage(text, type = 'info') {
                message.innerHTML = `
            <div class="alert alert-${escapeHtml(type)}" role="alert">
                ${escapeHtml(text)}
            </div>
        `;
            }

            function render() {
                const query = searchBox.value.trim().toLowerCase();

                const filtered = rows.filter(row => [
                    row.module,
                    row.controller,
                    row.method,
                    row.endpoint
                ].some(value =>
                    String(value ?? '').toLowerCase().includes(query)
                ));

                if (filtered.length === 0) {
                    body.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        Tidak ada endpoint yang cocok.
                    </td>
                </tr>
            `;

                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    return;
                }

                body.innerHTML = filtered.map(row => {
                    const registered = row.registry !== null;
                    const registry = row.registry ?? {};

                    return `
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="row-check"
                            value="${escapeHtml(row.endpoint)}"
                            aria-label="Pilih ${escapeHtml(row.endpoint)}"
                        >
                    </td>
                    <td>${escapeHtml(row.module)}</td>
                    <td>${escapeHtml(row.controller)}</td>
                    <td>${escapeHtml(row.method)}</td>
                    <td>
                        <code>${escapeHtml(row.endpoint)}</code>
                    </td>
                    <td>
                        <span class="badge ${
                            registered ? 'text-bg-success' : 'text-bg-secondary'
                        }">
                            ${registered ? 'Terdaftar' : 'Baru'}
                        </span>
                    </td>
                    <td>${registered ? escapeHtml(registry.is_routable) : '0'}</td>
                    <td>${registered ? escapeHtml(registry.is_allowed) : '0'}</td>
                </tr>
            `;
                }).join('');

                updateSelectAllState();
            }

            function selectedEndpoints() {
                return Array.from(
                    body.querySelectorAll('.row-check:checked')
                ).map(input => input.value);
            }

            function updateSelectAllState() {
                const checks = Array.from(body.querySelectorAll('.row-check'));
                const checkedCount = checks.filter(input => input.checked).length;

                selectAll.checked =
                    checks.length > 0 && checkedCount === checks.length;

                selectAll.indeterminate =
                    checkedCount > 0 && checkedCount < checks.length;
            }

            async function requestJson(url, options = {}) {
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

                const contentType = response.headers.get('content-type') || '';

                if (!contentType.includes('application/json')) {
                    throw new Error(
                        `Server mengembalikan respons non-JSON (HTTP ${response.status}).`
                    );
                }

                const result = await response.json();

                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Permintaan gagal.');
                }

                return result;
            }

            async function scan() {
                if (busy) return;

                busy = true;
                document.getElementById('btnScan').disabled = true;

                body.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Memindai module...
                </td>
            </tr>
        `;

                try {
                    const result = await requestJson(scanUrl);

                    rows = (result.data?.items ?? []).map(row => ({
                        ...row,
                        registry: row.registry ?? null
                    }));

                    render();

                    showMessage(
                        `Scan selesai. Ditemukan ${rows.length} endpoint. Database tidak diubah.`,
                        'success'
                    );
                } catch (error) {
                    showMessage(error.message, 'danger');
                } finally {
                    busy = false;
                    document.getElementById('btnScan').disabled = false;
                }
            }

            async function apply(action) {
                if (busy) return;

                const selected = selectedEndpoints();

                if (selected.length === 0) {
                    showMessage('Pilih minimal satu endpoint.', 'warning');
                    return;
                }

                const prompts = {
                    insert: `Insert ${selected.length} endpoint baru? Endpoint yang sudah ada akan dilewati.`,
                    update: `Update metadata ${selected.length} endpoint? Status routable dan allowed tetap dipertahankan.`,
                    delete: `Hapus ${selected.length} endpoint dari registry? Data relasi terkait dapat ikut terhapus sesuai foreign key.`
                };

                if (!window.confirm(prompts[action])) {
                    return;
                }

                busy = true;

                for (const id of [
                        'btnScan',
                        'btnInsert',
                        'btnUpdate',
                        'btnDelete'
                    ]) {
                    document.getElementById(id).disabled = true;
                }

                try {
                    const result = await requestJson(applyUrl, {
                        method: 'POST',
                        body: JSON.stringify({
                            action,
                            selected
                        })
                    });

                    const details = Object.entries(result.data ?? {})
                        .map(([key, value]) => `${key}: ${value}`)
                        .join(', ');

                    showMessage(
                        `${result.message}${details ? ' — ' + details : ''}`,
                        'success'
                    );

                    /*
                     * Muat ulang hasil scan setelah tindakan selesai.
                     * Scan berikutnya tetap read-only.
                     */
                    busy = false;
                    await scan();
                    return;
                } catch (error) {
                    showMessage(error.message, 'danger');
                } finally {
                    busy = false;

                    for (const id of [
                            'btnScan',
                            'btnInsert',
                            'btnUpdate',
                            'btnDelete'
                        ]) {
                        document.getElementById(id).disabled = false;
                    }
                }
            }

            document.getElementById('btnScan')
                .addEventListener('click', scan);

            document.getElementById('btnInsert')
                .addEventListener('click', () => apply('insert'));

            document.getElementById('btnUpdate')
                .addEventListener('click', () => apply('update'));

            document.getElementById('btnDelete')
                .addEventListener('click', () => apply('delete'));

            searchBox.addEventListener('input', render);

            selectAll.addEventListener('change', event => {
                body.querySelectorAll('.row-check').forEach(input => {
                    input.checked = event.target.checked;
                });

                updateSelectAllState();
            });

            body.addEventListener('change', event => {
                if (event.target.classList.contains('row-check')) {
                    updateSelectAllState();
                }
            });
        })();
    </script>

</body>

</html>
