<div class="container-fluid">
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
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="selectAll">
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
                    <td colspan="8" class="text-center">
                        Klik Scan Endpoint untuk memuat data.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="registryMessage" class="mt-3"></div>
</div>

<?= $this->section('jsFooterInline') ?>
<script>
    (() => {
        'use strict';

        const scanUrl = <?= json_encode(
                            site_url('backend/route-registry/api/scan')
                        ) ?>;

        const applyUrl = <?= json_encode(
                                site_url('backend/route-registry/api/apply')
                            ) ?>;

        const body = document.getElementById('registryBody');
        const message = document.getElementById('registryMessage');
        const searchBox = document.getElementById('searchBox');

        let rows = [];

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
            <div class="alert alert-${type}">
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
            ].some(value => String(value ?? '').toLowerCase().includes(query)));

            if (filtered.length === 0) {
                body.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        Tidak ada endpoint yang cocok.
                    </td>
                </tr>
            `;
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
                        >
                    </td>
                    <td>${escapeHtml(row.module)}</td>
                    <td>${escapeHtml(row.controller)}</td>
                    <td>${escapeHtml(row.method)}</td>
                    <td><code>${escapeHtml(row.endpoint)}</code></td>
                    <td>${registered ? 'Terdaftar' : 'Baru'}</td>
                    <td>${registered ? registry.is_routable : 0}</td>
                    <td>${registered ? registry.is_allowed : 0}</td>
                </tr>
            `;
            }).join('');
        }

        function selectedEndpoints() {
            return Array.from(
                body.querySelectorAll('.row-check:checked')
            ).map(input => input.value);
        }

        async function requestJson(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    ...(options.body ? {
                        'Content-Type': 'application/json'
                    } : {}),
                    ...(options.headers ?? {})
                },
                ...options
            });

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Permintaan gagal.');
            }

            return result;
        }

        async function scan() {
            body.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">Memindai module...</td>
            </tr>
        `;

            try {
                const result = await requestJson(scanUrl);

                rows = result.data.items ?? [];
                render();

                showMessage(
                    `Scan selesai. Ditemukan ${rows.length} endpoint. Database tidak diubah.`,
                    'success'
                );
            } catch (error) {
                showMessage(error.message, 'danger');
            }
        }

        async function apply(action) {
            const selected = selectedEndpoints();

            if (selected.length === 0) {
                showMessage('Pilih minimal satu endpoint.', 'warning');
                return;
            }

            const prompts = {
                insert: `Insert ${selected.length} endpoint baru?`,
                update: `Update metadata ${selected.length} endpoint?`,
                delete: `Hapus ${selected.length} endpoint dari registry? Data relasi terkait dapat ikut terhapus sesuai foreign key.`
            };

            if (!window.confirm(prompts[action])) {
                return;
            }

            try {
                const result = await requestJson(applyUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        action,
                        selected
                    })
                });

                showMessage(
                    `${result.message} ${JSON.stringify(result.data)}`,
                    'success'
                );

                await scan();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
        }

        document.getElementById('btnScan').addEventListener('click', scan);
        document.getElementById('btnInsert').addEventListener(
            'click', () => apply('insert')
        );
        document.getElementById('btnUpdate').addEventListener(
            'click', () => apply('update')
        );
        document.getElementById('btnDelete').addEventListener(
            'click', () => apply('delete')
        );

        searchBox.addEventListener('input', render);

        document.getElementById('selectAll').addEventListener(
            'change',
            event => {
                body.querySelectorAll('.row-check').forEach(input => {
                    input.checked = event.target.checked;
                });
            }
        );
    })();
</script>

<?= $this->endSection() ?>
