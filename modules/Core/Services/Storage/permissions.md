
| Permission konseptual             | Kegunaan                     |
| --------------------------------- | ---------------------------- |
| `storage.files.view`            | Melihat metadata file        |
| `storage.files.upload`          | Mengunggah file              |
| `storage.files.download`        | Mengunduh file               |
| `storage.files.delete`          | Meminta penghapusan file     |
| `storage.files.restore`         | Memulihkan file yang dihapus |
| `storage.files.version.view`    | Melihat riwayat versi        |
| `storage.files.version.restore` | Memulihkan versi             |
| `storage.disks.view`            | Melihat status disk          |
| `storage.disks.manage`          | Mengelola konfigurasi disk   |
| `storage.audit.view`            | Melihat audit penyimpanan    |
| `storage.quota.manage`          | Mengelola batas kapasitas    |

```
$storage = service('storage');

$file = $storage->upload(
    sourcePath: $uploadedFile->getTempName(),
    originalName: $uploadedFile->getClientName(),
    uploadedBy: (int) auth()->id()
);

$relationId = $storage->attach(
    fileId: (int) $file['id'],
    module: 'Erp',
    entityType: 'PurchaseOrder',
    entityId: (string) $purchaseOrderId,
    purpose: 'attachment'
);
```

```
$fileHandler = service('fileHandler');

$result = $fileHandler->upload(
    sourcePath: $temporaryPath,
    originalName: $originalName,
    uploadedBy: $userId,
    organizationId: $organizationId
);

$imageHandler = service('imageHandler');

$result = $imageHandler->upload(
    sourcePath: $temporaryPath,
    originalName: $originalName,
    uploadedBy: $userId,
    organizationId: $organizationId,
    maxWidth: 1200,
    maxHeight: 1200,
    quality: 85
);
```

SELECT
    f.id AS file_id,
    f.original_name,
    f.disk,
    f.organization_id,
    f.uploaded_by,
    r.module,
    r.entity_type,
    r.entity_id,
    r.purpose
FROM storage_files AS f
LEFT JOIN storage_file_relations AS r
    ON r.file_id = f.id
   AND r.deleted_at IS NULL
WHERE f.id = 501
  AND f.deleted_at IS NULL;

<form id="uploadForm">
    <input type="file" name="file" required>
    <button type="submit">Upload</button>
</form>

<script>
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    const response = await fetch('/api/v1/core/storage/upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });

    const result = await response.json();

    if (!response.ok) {
        alert(result.message || 'Upload gagal');
        return;
    }

    console.log('File ID:', result.data.id);
    console.log(result);
});
</script>







```
UserProfilePhotoService.php

$profilePhotos = new UserProfilePhotoService();

$profilePhotos->setActive(
    userId: $loggedInUserId,
    fileId: (int) $uploadedFile['id']
);


$photo = $profilePhotos->getActive($loggedInUserId);
$history = $profilePhotos->getHistory($loggedInUserId);
```
