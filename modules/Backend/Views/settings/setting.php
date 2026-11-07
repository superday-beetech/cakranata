<?php

$formatSettingValue = static function (mixed $value): string {
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return '';
    }

    if (is_array($value) || is_object($value)) {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '';
    }

    return (string) $value;
};

$typeOptions = [
    'string'  => 'String',
    'integer' => 'Integer',
    'float'   => 'Float',
    'boolean' => 'Boolean',
    'json'    => 'JSON',
];

$activeGroupId = array_key_first($groups ?? []);
?>

<div class="container-fluid py-3">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">
                <?= esc($label ?? 'Pengaturan Aplikasi') ?>
            </h4>
            <p class="text-muted mb-0">
                Kelola nilai pengaturan berdasarkan class dan context.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#addClassModal">
            <i class="bi bi-plus-lg me-1"></i>
            Tambah Class
        </button>
    </div>


    <?php if (empty($groups)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-sliders fs-1 text-muted"></i>
                <h5 class="mt-3">Belum ada pengaturan</h5>
                <p class="text-muted">
                    Tambahkan class dan properti pertama untuk mulai mengelola pengaturan.
                </p>
            </div>
        </div>

    <?php else: ?>

        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
            <?php foreach ($groups as $groupId => $group): ?>
                <?php $isActive = $groupId === $activeGroupId; ?>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link <?= $isActive ? 'active' : '' ?>"
                        id="tab-<?= esc($groupId) ?>"
                        data-bs-toggle="tab"
                        data-bs-target="#pane-<?= esc($groupId) ?>"
                        type="button"
                        role="tab"
                        aria-controls="pane-<?= esc($groupId) ?>"
                        aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                        <?= esc($group['name']) ?>

                        <span class="badge text-bg-secondary ms-1">
                            <?= count($group['items']) ?>
                        </span>

                        <?php if ($group['context'] !== null): ?>
                            <span class="badge text-bg-info ms-1">
                                <?= esc($group['context']) ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content" id="settingsTabContent">

            <?php foreach ($groups as $groupId => $group): ?>
                <?php $isActive = $groupId === $activeGroupId; ?>

                <div
                    class="tab-pane fade <?= $isActive ? 'show active' : '' ?>"
                    id="pane-<?= esc($groupId) ?>"
                    role="tabpanel"
                    aria-labelledby="tab-<?= esc($groupId) ?>"
                    tabindex="0">
                    <form
                        action="<?= site_url('backend/settings/bulk-save') ?>"
                        method="post"
                        class="setting-form"
                        data-group-id="<?= esc($groupId) ?>">
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="group"
                            value="<?= esc($group['class']) ?>">

                        <input
                            type="hidden"
                            name="context"
                            value="<?= esc($group['context'] ?? '') ?>">

                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                                <div>
                                    <div class="fw-semibold">
                                        <?= esc($group['name']) ?>
                                    </div>
                                    <small class="text-muted">
                                        Class:
                                        <code><?= esc($group['class']) ?></code>
                                    </small>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm js-add-row">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Tambah Properti
                                </button>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 settings-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width: 180px;">Properti</th>
                                                <th style="min-width: 130px;">Tipe</th>
                                                <th style="min-width: 260px;">Nilai</th>
                                                <th class="text-center" style="width: 90px;">Aksi</th>
                                            </tr>
                                        </thead>

                                        <tbody class="js-settings-rows">
                                            <?php foreach ($group['items'] as $index => $item): ?>
                                                <?php
                                                $type = in_array(
                                                    $item['type'],
                                                    array_keys($typeOptions),
                                                    true
                                                ) ? $item['type'] : 'string';

                                                $value = $formatSettingValue($item['value']);
                                                $isBoolean = $type === 'boolean';
                                                $isChecked = $isBoolean
                                                    && in_array(
                                                        strtolower($value),
                                                        ['true', '1', 'yes', 'on'],
                                                        true
                                                    );
                                                ?>

                                                <tr class="js-setting-row">
                                                    <td>
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm"
                                                            value="<?= esc($item['property']) ?>"
                                                            readonly>

                                                        <input
                                                            type="hidden"
                                                            name="rows[<?= $index ?>][property]"
                                                            value="<?= esc($item['property']) ?>">
                                                    </td>

                                                    <td>
                                                        <select
                                                            class="form-select form-select-sm js-row-type"
                                                            name="rows[<?= $index ?>][type]">
                                                            <?php foreach ($typeOptions as $optionValue => $optionLabel): ?>
                                                                <option
                                                                    value="<?= esc($optionValue) ?>"
                                                                    <?= $type === $optionValue ? 'selected' : '' ?>>
                                                                    <?= esc($optionLabel) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>

                                                    <td>
                                                        <div class="js-value-container">
                                                            <?php if ($isBoolean): ?>
                                                                <div class="form-check form-switch">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="form-check-input js-row-boolean-switch"
                                                                        role="switch"
                                                                        id="bool-<?= esc($groupId) ?>-<?= $index ?>"
                                                                        <?= $isChecked ? 'checked' : '' ?>>

                                                                    <label
                                                                        class="form-check-label js-row-boolean-label"
                                                                        for="bool-<?= esc($groupId) ?>-<?= $index ?>">
                                                                        <?= $isChecked ? 'Active' : 'Inactive' ?>
                                                                    </label>
                                                                </div>

                                                                <input
                                                                    type="hidden"
                                                                    class="js-row-boolean-value"
                                                                    name="rows[<?= $index ?>][value]"
                                                                    value="<?= $isChecked ? 'true' : 'false' ?>">
                                                            <?php else: ?>
                                                                <textarea
                                                                    class="form-control form-control-sm js-row-value"
                                                                    name="rows[<?= $index ?>][value]"
                                                                    rows="<?= $type === 'json' ? 4 : 2 ?>"
                                                                    spellcheck="false"><?= esc($value) ?></textarea>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <td class="text-center">
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-danger btn-sm js-remove-existing"
                                                            title="Hapus override">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="js-forget-container"></div>

                                <div class="js-new-row-container"></div>
                            </div>

                            <div class="card-footer bg-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                                <small class="text-muted">
                                    Perubahan hanya disimpan untuk tab ini.
                                </small>

                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-1"></i>
                                    Simpan Semua Perubahan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<!-- Modal tambah class/properti awal -->
<div
    class="modal fade"
    id="addClassModal"
    tabindex="-1"
    aria-labelledby="addClassModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form
            action="<?= site_url('backend/settings/add-class') ?>"
            method="post"
            class="modal-content"
            id="addClassForm">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h5 class="modal-title" id="addClassModalLabel">
                    Tambah Class Pengaturan
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small">
                    Ini menambahkan entri pengaturan ke database Settings,
                    bukan membuat file class PHP baru.
                </div>

                <div class="mb-3">
                    <label for="newClass" class="form-label">
                        Nama Class
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="newClass"
                        name="class"
                        placeholder="Contoh: App atau Config\App"
                        required>
                    <div class="form-text">
                        Gunakan nama class konfigurasi yang sesuai dengan format package Settings.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="newProperty" class="form-label">
                        Properti Awal
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="newProperty"
                        name="property"
                        placeholder="Contoh: maintenanceMode"
                        pattern="[A-Za-z_][A-Za-z0-9_]*"
                        required>
                </div>

                <div class="mb-3">
                    <label for="newType" class="form-label">
                        Tipe Nilai
                    </label>
                    <select
                        class="form-select"
                        id="newType"
                        name="type">
                        <?php foreach ($typeOptions as $optionValue => $optionLabel): ?>
                            <option value="<?= esc($optionValue) ?>">
                                <?= esc($optionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3" id="newValueContainer">
                    <label for="newValue" class="form-label">
                        Nilai Awal
                    </label>

                    <textarea
                        class="form-control"
                        id="newValue"
                        name="value"
                        rows="3"
                        required></textarea>

                    <div class="form-text" id="newValueHelp">
                        Masukkan nilai awal properti.
                    </div>
                </div>

                <div class="mb-3 d-none" id="newBooleanContainer">
                    <label class="form-label d-block">Nilai Boolean</label>

                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="newValueSwitch">
                        <label
                            class="form-check-label"
                            for="newValueSwitch"
                            id="newBooleanLabel">
                            Nonaktif (false)
                        </label>
                    </div>

                    <input
                        type="hidden"
                        name="value"
                        id="newBooleanValue"
                        value="false"
                        disabled>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>
                    Tambahkan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const typeOptions = {
            string: 'String',
            integer: 'Integer',
            float: 'Float',
            boolean: 'Boolean',
            json: 'JSON'
        };

        const tabStorageKey = 'backend-settings-active-tab';
        const tabs = document.querySelectorAll(
            '#settingsTabs [data-bs-toggle="tab"]'
        );

        // Ingat tab terakhir yang aktif.
        if (tabs.length > 0) {
            const savedTab = localStorage.getItem(tabStorageKey);
            const savedButton = savedTab ?
                document.querySelector(
                    '#settingsTabs [data-bs-target="' + CSS.escape(savedTab) + '"]'
                ) :
                null;

            if (savedButton && window.bootstrap) {
                bootstrap.Tab.getOrCreateInstance(savedButton).show();
            }

            tabs.forEach(function(tab) {
                tab.addEventListener('shown.bs.tab', function(event) {
                    localStorage.setItem(
                        tabStorageKey,
                        event.target.getAttribute('data-bs-target')
                    );
                });
            });
        }

        function createElement(tag, className, text) {
            const element = document.createElement(tag);

            if (className) {
                element.className = className;
            }

            if (text !== undefined) {
                element.textContent = text;
            }

            return element;
        }

        function buildValueControl(type, name, value, idPrefix) {
            const container = createElement('div', 'js-value-container');

            if (type === 'boolean') {
                const checked = String(value).toLowerCase() === 'true';

                const wrapper = createElement('div', 'form-check form-switch');
                const switchId = idPrefix + '-switch';

                const input = createElement('input', 'form-check-input js-row-boolean-switch');
                input.type = 'checkbox';
                input.id = switchId;
                input.setAttribute('role', 'switch');
                input.checked = checked;

                const label = createElement('label', 'form-check-label js-row-boolean-label');
                label.htmlFor = switchId;
                label.textContent = checked ? 'Active' : 'Inactive';

                const hidden = createElement('input', 'js-row-boolean-value');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = checked ? 'true' : 'false';

                wrapper.append(input, label);
                container.append(wrapper, hidden);

                return container;
            }

            const textarea = createElement('textarea', 'form-control form-control-sm js-row-value');
            textarea.name = name;
            textarea.rows = type === 'json' ? 4 : 2;
            textarea.spellcheck = false;
            textarea.value = value ?? '';

            container.append(textarea);

            return container;
        }

        function rebuildValueControl(row, type, index) {
            const container = row.querySelector('.js-value-container');
            const propertyInput = row.querySelector(
                'input[name$="[property]"]'
            );

            if (!container || !propertyInput) {
                return;
            }

            const valueName = 'rows[' + index + '][value]';
            const currentValue = type === 'boolean' ?
                'false' :
                (row.querySelector('.js-row-value')?.value ?? '');

            container.replaceWith(
                buildValueControl(
                    type,
                    valueName,
                    currentValue,
                    'setting-' + index
                )
            );
        }

        function createTypeSelect(name, selectedType) {
            const select = createElement('select', 'form-select form-select-sm js-row-type');
            select.name = name;

            Object.keys(typeOptions).forEach(function(type) {
                const option = createElement('option', '', typeOptions[type]);
                option.value = type;
                option.selected = type === selectedType;
                select.append(option);
            });

            return select;
        }

        function createNewRow(form, index) {
            const row = createElement('tr', 'js-setting-row js-new-setting-row');

            const propertyCell = createElement('td');
            const propertyInput = createElement('input', 'form-control form-control-sm');
            propertyInput.type = 'text';
            propertyInput.name = 'rows[' + index + '][property]';
            propertyInput.placeholder = 'Nama properti';
            propertyInput.pattern = '[A-Za-z_][A-Za-z0-9_]*';
            propertyInput.required = true;
            propertyCell.append(propertyInput);

            const typeCell = createElement('td');
            const typeSelect = createTypeSelect(
                'rows[' + index + '][type]',
                'string'
            );
            typeCell.append(typeSelect);

            const valueCell = createElement('td');
            valueCell.append(
                buildValueControl(
                    'string',
                    'rows[' + index + '][value]',
                    '',
                    'setting-' + index
                )
            );

            const actionCell = createElement('td', 'text-center');
            const removeButton = createElement('button', 'btn btn-outline-danger btn-sm');
            removeButton.type = 'button';
            removeButton.title = 'Batalkan baris baru';
            removeButton.innerHTML = '<i class="bi bi-x-lg"></i>';
            actionCell.append(removeButton);

            row.append(propertyCell, typeCell, valueCell, actionCell);

            removeButton.addEventListener('click', function() {
                row.remove();
            });

            typeSelect.addEventListener('change', function() {
                rebuildValueControl(row, typeSelect.value, index);
            });

            return row;
        }

        // Switch boolean pada semua tab: sinkronkan label dan hidden input.
        document.addEventListener('change', function(event) {
            const switchInput = event.target.closest('.js-row-boolean-switch');

            if (switchInput) {
                const container = switchInput.closest('.js-value-container');
                const hidden = container?.querySelector('.js-row-boolean-value');
                const label = container?.querySelector('.js-row-boolean-label');

                if (hidden) {
                    hidden.value = switchInput.checked ? 'true' : 'false';
                }

                if (label) {
                    label.textContent = switchInput.checked ?
                        'Active' :
                        'Inactive';
                }
            }
        });

        // Tambah properti baru pada tab yang dipilih.
        document.querySelectorAll('.setting-form').forEach(function(form) {
            const tbody = form.querySelector('.js-settings-rows');
            const addButton = form.querySelector('.js-add-row');
            let nextIndex = form.querySelectorAll('.js-setting-row').length;

            addButton?.addEventListener('click', function() {
                const row = createNewRow(form, nextIndex++);
                tbody.append(row);

                row.querySelector('input[name$="[property]"]')?.focus();
            });

            // Hapus override: tandai forget[] lalu hilangkan baris dari tampilan.
            form.addEventListener('click', function(event) {
                const button = event.target.closest('.js-remove-existing');

                if (!button || !form.contains(button)) {
                    return;
                }

                const row = button.closest('.js-setting-row');
                const propertyInput = row?.querySelector(
                    'input[name$="[property]"]'
                );

                if (!row || !propertyInput) {
                    return;
                }

                const property = propertyInput.value;

                if (!window.confirm(
                        'Hapus override "' + property + '"? Nilai default konfigurasi dapat digunakan kembali jika tersedia.'
                    )) {
                    return;
                }

                const hidden = createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'forget[]';
                hidden.value = property;

                form.querySelector('.js-forget-container').append(hidden);
                row.remove();
            });

            // Validasi tipe JSON sebelum dikirim ke controller.
            form.addEventListener('submit', function(event) {
                const rows = form.querySelectorAll('.js-setting-row');

                for (const row of rows) {
                    const propertyInput = row.querySelector(
                        'input[name$="[property]"]'
                    );
                    const typeSelect = row.querySelector('.js-row-type');

                    if (!propertyInput || !typeSelect) {
                        continue;
                    }

                    const property = propertyInput.value.trim();

                    if (property === '') {
                        event.preventDefault();
                        propertyInput.focus();
                        window.alert('Nama properti tidak boleh kosong.');
                        return;
                    }

                    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(property)) {
                        event.preventDefault();
                        propertyInput.focus();
                        window.alert(
                            'Nama properti hanya boleh berisi huruf, angka, dan underscore; karakter pertama harus huruf atau underscore.'
                        );
                        return;
                    }

                    if (typeSelect.value === 'json') {
                        const valueInput = row.querySelector('.js-row-value');

                        if (!valueInput) {
                            continue;
                        }

                        try {
                            JSON.parse(valueInput.value);
                        } catch (error) {
                            event.preventDefault();
                            valueInput.focus();
                            window.alert(
                                'Nilai JSON untuk "' + property + '" tidak valid.'
                            );
                            return;
                        }
                    }
                }
            });
        });

        // Modal tambah class dan properti awal.
        const newType = document.getElementById('newType');
        const newValue = document.getElementById('newValue');
        const newValueContainer = document.getElementById('newValueContainer');
        const newBooleanContainer = document.getElementById('newBooleanContainer');
        const newValueSwitch = document.getElementById('newValueSwitch');
        const newBooleanValue = document.getElementById('newBooleanValue');
        const newBooleanLabel = document.getElementById('newBooleanLabel');
        const newValueHelp = document.getElementById('newValueHelp');
        const addClassForm = document.getElementById('addClassForm');

        function syncModalBoolean() {
            if (newBooleanValue && newValueSwitch) {
                newBooleanValue.value = newValueSwitch.checked ? 'true' : 'false';
            }

            if (newBooleanLabel && newValueSwitch) {
                newBooleanLabel.textContent = newValueSwitch.checked ?
                    'Aktif (true)' :
                    'Nonaktif (false)';
            }
        }

        function updateModalType() {
            if (
                !newType ||
                !newValue ||
                !newValueContainer ||
                !newBooleanContainer ||
                !newBooleanValue
            ) {
                return;
            }

            const isBoolean = newType.value === 'boolean';

            newValueContainer.classList.toggle('d-none', isBoolean);
            newBooleanContainer.classList.toggle('d-none', !isBoolean);

            newValue.disabled = isBoolean;
            newValue.required = !isBoolean;
            newBooleanValue.disabled = !isBoolean;

            if (newValueHelp) {
                newValueHelp.textContent = newType.value === 'json' ?
                    'Masukkan JSON yang valid, misalnya {"enabled":true}.' :
                    'Masukkan nilai awal properti.';
            }

            if (isBoolean) {
                syncModalBoolean();
            }
        }

        newType?.addEventListener('change', updateModalType);
        newValueSwitch?.addEventListener('change', syncModalBoolean);

        addClassForm?.addEventListener('submit', function(event) {
            if (newType?.value === 'json' && newValue) {
                try {
                    JSON.parse(newValue.value);
                } catch (error) {
                    event.preventDefault();
                    newValue.focus();
                    window.alert('Nilai JSON tidak valid.');
                }
            }
        });

        updateModalType();
    });
</script>
