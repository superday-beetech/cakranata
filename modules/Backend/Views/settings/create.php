<!DOCTYPE html>
<html>
<head>
    <title>Tambah Setting Baru</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h2>Tambah Setting Baru</h2>
        <form action="<?= site_url('settings/store') ?>" method="post">
            <div class="mb-3">
                <label>Nama (key)</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Label</label>
                <input type="text" name="label" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Value</label>
                <input type="text" name="value" class="form-control">
            </div>
            <div class="mb-3">
                <label>Tipe Input</label>
                <select name="input_type" class="form-control">
                    <option value="input">Input</option>
                    <option value="textarea">Textarea</option>
                    <option value="radio">Radio</option>
                    <option value="dropdown">Dropdown</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Tipe</label>
                <input type="text" name="type" class="form-control" value="general">
            </div>
            <div class="mb-3">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="1">
            </div>

            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="<?= site_url('settings') ?>" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</body>
</html>
