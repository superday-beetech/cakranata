<div class="container mt-4">
  <h1 class="mb-4">Baru List</h1>
  <a href="<?= site_url('core/baru/create') ?>" class="btn btn-primary mb-3">+ Create New</a>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $item): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= esc($item['name']) ?></td>
          <td>
            <a href="<?= site_url('core/baru/edit/' . $item['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
            <a href="<?= site_url('core/baru/delete/' . $item['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
