<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Organisasi</h4>
            <p class="text-muted mb-0">
                Kelola organisasi yang dapat diakses oleh pengguna.
            </p>
        </div>

        <button type="button" id="btnAddOrganization"
            class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Organisasi
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <label for="searchBox" class="form-label">
                        Pencarian
                    </label>
                    <input type="search" id="searchBox"
                        class="form-control"
                        placeholder="Kode, nama, atau nama legal...">
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="filterStatus" class="form-label">
                        Status
                    </label>
                    <select id="filterStatus" class="form-select">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                        <option value="suspended">Ditangguhkan</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="filterType" class="form-label">
                        Tipe Organisasi
                    </label>
                    <select id="filterType" class="form-select">
                        <option value="">Semua tipe</option>
                        <option value="company">Perusahaan</option>
                        <option value="cooperative">Koperasi</option>
                        <option value="government">Pemerintahan</option>
                        <option value="business_unit">Unit Usaha</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="pageSizeSelect" class="form-label">
                        Baris per halaman
                    </label>
                    <select id="pageSizeSelect" class="form-select">
                        <option value="10">10 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filterDateFrom" class="form-label">
                        Dibuat mulai
                    </label>
                    <input type="date" id="filterDateFrom"
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="filterDateTo" class="form-label">
                        Dibuat sampai
                    </label>
                    <input type="date" id="filterDateTo"
                        class="form-control">
                </div>

                <div class="col-md-6 d-flex align-items-end justify-content-md-end gap-2">
                    <button type="button" id="btnApplyFilter"
                        class="btn btn-primary">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>

                    <button type="button" id="btnResetFilter"
                        class="btn btn-outline-secondary">
                        Reset
                    </button>

                    <button type="button" id="btnRefresh"
                        class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="organizationTable"></div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <small id="info" class="text-muted">
                    Menyiapkan data...
                </small>
            </div>
        </div>
    </div>
</div>

<?= $this->section('jsFooterInline') ?>
<script>
    (() => {
        'use strict';

        const API_URL = '<?= site_url('organization/api/v1/') ?>';

        const elements = {
            search: document.getElementById('searchBox'),
            pageSize: document.getElementById('pageSizeSelect'),
            status: document.getElementById('filterStatus'),
            type: document.getElementById('filterType'),
            dateFrom: document.getElementById('filterDateFrom'),
            dateTo: document.getElementById('filterDateTo'),
            apply: document.getElementById('btnApplyFilter'),
            reset: document.getElementById('btnResetFilter'),
            refresh: document.getElementById('btnRefresh'),
            info: document.getElementById('info'),
            add: document.getElementById('btnAddOrganization'),
        };

        let filters = {
            q: '',
            status: '',
            organization_type: '',
            created_from: '',
            created_to: '',
        };

        let searchTimer = null;

        if (typeof Tabulator === 'undefined') {
            elements.info.textContent =
                'Tabulator belum dimuat oleh layout backend.';
            console.error('Tabulator belum tersedia.');
            return;
        }

        /**
         * Tabulator mengirim parameter pagination dan sorting
         * ke API. Filter tambahan dikirim melalui query string.
         */
        const table = new Tabulator('#organizationTable', {
            layout: 'fitColumns',
            height: '60vh',
            placeholder: 'Tidak ada data organisasi.',
            index: 'id',

            pagination: true,
            paginationMode: 'remote',
            paginationSize: Number(elements.pageSize.value),
            paginationSizeSelector: [10, 25, 50, 100],

            sortMode: 'remote',
            filterMode: 'remote',

            ajaxURL: API_URL,

            ajaxURLGenerator: function(url, config, params) {
                const query = new URLSearchParams();

                query.set('page', params.page || 1);
                query.set('perPage', params.size || 10);

                if (params.sorters && params.sorters.length) {
                    const sorter = params.sorters[0];
                    query.set('sort', sorter.field);
                    query.set('order', sorter.dir);
                }

                Object.entries(filters).forEach(([key, value]) => {
                    if (value !== '') {
                        query.set(key, value);
                    }
                });

                return `${url}?${query.toString()}`;
            },

            ajaxResponse: function(url, params, response) {
                /*
                 * Mendukung respons API:
                 * { data: [...], pagination: { totalPages: 3, total: 25 } }
                 * atau format Tabulator { data: [...], last_page: 3 }.
                 */
                const body = response?.data;
                const pagination = response?.pagination ??
                    body?.pagination ?? {};

                let rows = Array.isArray(body) ?
                    body :
                    body?.items ??
                    body?.results ??
                    body?.data ??
                    response?.items ??
                    response?.results ?? [];

                if (!Array.isArray(rows)) {
                    rows = [];
                }

                const total = Number(
                    pagination.total ??
                    pagination.totalItems ??
                    response?.total ??
                    rows.length
                );

                const pageSize = Number(params.size || elements.pageSize.value);

                const lastPage = Number(
                    pagination.totalPages ??
                    pagination.last_page ??
                    response?.last_page ??
                    Math.max(1, Math.ceil(total / pageSize))
                );

                elements.info.textContent =
                    `Total ${total.toLocaleString('id-ID')} organisasi`;

                return {
                    data: rows,
                    last_page: Math.max(1, lastPage),
                };
            },

            ajaxError: function(error) {
                console.error('Gagal memuat organisasi:', error);
                elements.info.textContent =
                    'Gagal memuat data. Periksa koneksi atau respons API.';
            },

            columns: [{
                    title: 'Kode',
                    field: 'code',
                    minWidth: 110,
                    headerSort: true,
                },
                {
                    title: 'Nama Organisasi',
                    field: 'name',
                    minWidth: 200,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const wrapper = document.createElement('div');

                        const name = document.createElement('div');
                        name.className = 'fw-semibold';
                        name.textContent = row.name || '-';
                        wrapper.appendChild(name);

                        if (row.legal_name) {
                            const legal = document.createElement('small');
                            legal.className = 'text-muted';
                            legal.textContent = row.legal_name;
                            wrapper.appendChild(legal);
                        }

                        return wrapper;
                    },
                },
                {
                    title: 'Tipe',
                    field: 'organization_type',
                    minWidth: 130,
                    formatter: function(cell) {
                        return cell.getValue() || '-';
                    },
                },
                {
                    title: 'Status',
                    field: 'status',
                    width: 125,
                    formatter: function(cell) {
                        const status = String(cell.getValue() || '')
                            .toLowerCase();

                        const labels = {
                            active: 'Aktif',
                            inactive: 'Tidak aktif',
                            suspended: 'Ditangguhkan',
                        };

                        const badge = document.createElement('span');
                        badge.className = 'badge ' + (
                            status === 'active' ?
                            'bg-success' :
                            status === 'suspended' ?
                            'bg-warning text-dark' :
                            'bg-secondary'
                        );

                        badge.textContent = labels[status] || status || '-';

                        return badge;
                    },
                },
                {
                    title: 'Zona Waktu',
                    field: 'timezone',
                    minWidth: 130,
                    formatter: cell => cell.getValue() || '-',
                },
                {
                    title: 'Mata Uang',
                    field: 'currency',
                    width: 100,
                    formatter: cell => cell.getValue() || '-',
                },
                {
                    title: 'Aksi',
                    field: 'id',
                    width: 150,
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: function() {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'd-flex justify-content-center gap-1';

                        const detail = document.createElement('button');
                        detail.type = 'button';
                        detail.className = 'btn btn-sm btn-outline-primary';
                        detail.textContent = 'Detail';
                        detail.dataset.action = 'detail';

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'btn btn-sm btn-outline-danger';
                        remove.textContent = 'Hapus';
                        remove.dataset.action = 'delete';

                        wrapper.append(detail, remove);
                        return wrapper;
                    },
                    cellClick: async function(event, cell) {
                        const action = event.target.closest('[data-action]')
                            ?.dataset.action;

                        if (!action) {
                            return;
                        }

                        const row = cell.getRow().getData();

                        if (action === 'detail') {
                            const details = [
                                `Kode: ${row.code || '-'}`,
                                `Nama: ${row.name || '-'}`,
                                `Nama legal: ${row.legal_name || '-'}`,
                                `Tipe: ${row.organization_type || '-'}`,
                                `Status: ${row.status || '-'}`,
                                `Zona waktu: ${row.timezone || '-'}`,
                                `Mata uang: ${row.currency || '-'}`,
                            ].join('\n');

                            window.alert(details);
                            return;
                        }

                        if (action === 'delete') {
                            if (!window.confirm(
                                    `Hapus organisasi "${row.name}"?` +
                                    '\nPastikan organisasi ini memang boleh dihapus.'
                                )) {
                                return;
                            }

                            try {
                                const response = await requestJson(
                                    `${API_URL}/${encodeURIComponent(row.id)}`, {
                                        method: 'DELETE'
                                    }
                                );

                                if (
                                    response?.status !== 'success' &&
                                    response?.error !== false
                                ) {
                                    throw new Error(
                                        response?.message || 'Gagal menghapus organisasi.'
                                    );
                                }

                                await table.setPage(1);
                                await table.replaceData();
                            } catch (error) {
                                console.error(error);
                                window.alert(
                                    error.message || 'Gagal menghapus organisasi.'
                                );
                            }
                        }
                    },
                },
            ],
        });

        /**
         * Menggunakan apiFetch proyek jika tersedia.
         * Jika tidak, gunakan fetch biasa.
         */
        async function requestJson(url, options = {}) {
            let result;

            if (typeof window.apiFetch === 'function') {
                result = await window.apiFetch(url, options);
            } else {
                const headers = new Headers(options.headers || {});
                headers.set('Accept', 'application/json');

                if (options.method && options.method !== 'GET') {
                    headers.set('Content-Type', 'application/json');

                    const csrfMeta = document.querySelector(
                        'meta[name="csrf-token"]'
                    );

                    if (csrfMeta) {
                        headers.set('X-CSRF-TOKEN', csrfMeta.content);
                    }
                }

                result = await fetch(url, {
                    ...options,
                    headers,
                    credentials: 'same-origin',
                });
            }

            if (result instanceof Response) {
                const json = await result.json();

                if (!result.ok) {
                    throw new Error(
                        json?.message || `HTTP ${result.status}`
                    );
                }

                return json;
            }

            return result;
        }

        function collectFilters() {
            filters = {
                q: elements.search.value.trim(),
                status: elements.status.value,
                organization_type: elements.type.value,
                created_from: elements.dateFrom.value,
                created_to: elements.dateTo.value,
            };
        }

        async function applyFilters() {
            collectFilters();
            await table.setPage(1);
            await table.replaceData();
        }

        elements.apply.addEventListener('click', applyFilters);

        elements.search.addEventListener('input', () => {
            window.clearTimeout(searchTimer);

            searchTimer = window.setTimeout(() => {
                applyFilters();
            }, 350);
        });

        elements.pageSize.addEventListener('change', async () => {
            await table.setPageSize(Number(elements.pageSize.value));
            await table.setPage(1);
            await table.replaceData();
        });

        elements.refresh.addEventListener('click', async () => {
            if (table.modules.page) {
                await table.setPage(1);
            }

            await table.replaceData();
        });

        elements.reset.addEventListener('click', async () => {
            elements.search.value = '';
            elements.status.value = '';
            elements.type.value = '';
            elements.dateFrom.value = '';
            elements.dateTo.value = '';

            collectFilters();

            await table.setPage(1);
            await table.replaceData();
        });

        elements.add.addEventListener('click', () => {
            /*
             * Sambungkan ke modal tambah organisasi
             * setelah form dan kontrak API create dipastikan.
             */
            window.dispatchEvent(
                new CustomEvent('organization:create')
            );
        });

        elements.dateFrom.addEventListener('change', () => {
            if (
                elements.dateFrom.value &&
                elements.dateTo.value &&
                elements.dateFrom.value > elements.dateTo.value
            ) {
                elements.dateTo.setCustomValidity(
                    'Tanggal sampai harus sama atau setelah tanggal mulai.'
                );
                elements.dateTo.reportValidity();
            } else {
                elements.dateTo.setCustomValidity('');
            }
        });

        elements.dateTo.addEventListener('change', () => {
            if (
                elements.dateFrom.value &&
                elements.dateTo.value &&
                elements.dateFrom.value > elements.dateTo.value
            ) {
                elements.dateTo.setCustomValidity(
                    'Tanggal sampai harus sama atau setelah tanggal mulai.'
                );
                elements.dateTo.reportValidity();
            } else {
                elements.dateTo.setCustomValidity('');
            }
        });

    })();
</script>
<?= $this->endSection() ?>
