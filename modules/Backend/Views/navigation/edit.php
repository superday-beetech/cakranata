<!-- Contoh input title, url, icon, dll -->

<!-- 1. Toggle Status Aktif -->
<div class="mb-3 form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
        <?= (old('is_active', $menu['is_active'] ?? 1) == 1) ? 'checked' : '' ?>>
    <label class="form-check-label fw-bold" for="is_active">Status Menu Aktif</label>
</div>

<!-- 2. Checkbox Hak Akses Group Role -->
<div class="mb-3">
    <label class="form-label fw-bold">Hak Akses Group Role</label>
    <div class="d-flex flex-wrap gap-3">
        <?php foreach ($allGroups as $group): ?>
        <div class="form-check">
            <!-- in_array mengecek apakah ID group ada di data yang sudah terpilih sebelumnya -->
            <input class="form-check-input" type="checkbox" name="groups[]"
                value="<?= $group['id'] ?>"
                id="group_<?= $group['id'] ?>"
                <?= in_array($group['id'], $selectedGroups) ? 'checked' : '' ?>>
            <label class="form-check-label"
                for="group_<?= $group['id'] ?>">
                <?= esc($group['name']) ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
</div>
