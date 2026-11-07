<?php
$profile = $profile ?? [];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= esc($title) ?></h4>
        <a href="<?= site_url('rbac/users') ?>" class="btn btn-outline-secondary">
            Kembali
        </a>
    </div>

    <form method="post" action="<?= esc($action, 'attr') ?>">
        <?= csrf_field() ?>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Akun Pengguna</h5>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input
                        class="form-control"
                        type="text"
                        id="username"
                        name="username"
                        maxlength="30"
                        required
                        value="<?= esc(old('username', $user['username'] ?? ''), 'attr') ?>">
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Profil Pribadi</h5>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                        <input
                            class="form-control"
                            id="nama_lengkap"
                            name="nama_lengkap"
                            maxlength="150"
                            value="<?= esc(old('nama_lengkap', $profile['nama_lengkap'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="nama_panggilan" class="form-label">Nama Panggilan</label>
                        <input
                            class="form-control"
                            id="nama_panggilan"
                            name="nama_panggilan"
                            maxlength="80"
                            value="<?= esc(old('nama_panggilan', $profile['nama_panggilan'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="nomor_pegawai" class="form-label">Nomor Pegawai</label>
                        <input
                            class="form-control"
                            id="nomor_pegawai"
                            name="nomor_pegawai"
                            maxlength="50"
                            value="<?= esc(old('nomor_pegawai', $profile['nomor_pegawai'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                        <input
                            class="form-control"
                            type="date"
                            id="tanggal_lahir"
                            name="tanggal_lahir"
                            value="<?= esc(old('tanggal_lahir', $profile['tanggal_lahir'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                        <?php $gender = old('jenis_kelamin', $profile['jenis_kelamin'] ?? ''); ?>

                        <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= $gender === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $gender === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="nomor_telepon_alternatif" class="form-label">
                            Telepon Alternatif
                        </label>
                        <input
                            class="form-control"
                            id="nomor_telepon_alternatif"
                            name="nomor_telepon_alternatif"
                            maxlength="25"
                            value="<?= esc(old('nomor_telepon_alternatif', $profile['nomor_telepon_alternatif'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="col-12 mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea
                            class="form-control"
                            id="alamat"
                            name="alamat"
                            rows="3"><?= esc(old('alamat', $profile['alamat'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="<?= site_url('rbac/users') ?>" class="btn btn-light">Batal</a>
    </form>
</div>
