<?php

if (! function_exists('sanitizeSettingHtml')) {
    function sanitizeSettingHtml(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Step 1: Decode entities
        $decoded = htmlspecialchars_decode($value, ENT_QUOTES);

        // Step 2: Izinkan hanya tag aman tertentu
        $allowedTags = '<p><a><br><b><i><strong><em><ul><ol><li><span>';
        $sanitized   = strip_tags($decoded, $allowedTags);

        // Step 3 (Opsional): Bersihkan atribut javascript: pada tag <a> jika ada
        $sanitized   = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $sanitized);

        return trim($sanitized);
    }
}

if (!function_exists('sanitizeSettingHtml2')) {
    /**
     * Decode HTML entities & sanitize HTML content
     * @param  string|null $value
     * @return string
     */
    function sanitizeSettingHtml2(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Step 1: decode entities
        $decoded = htmlspecialchars_decode($value, ENT_QUOTES);

        // Step 2: basic strip unwanted tags, allow only safe tags
        // Sesuaikan tag sesuai kebutuhan
        $allowedTags = '<p><a><br><b><i><strong><em><ul><ol><li><span>';
        $sanitized   = strip_tags($decoded, $allowedTags);

        return trim($sanitized);
    }
}
if (!function_exists('uploadSettingFile')) {
    /**
     * Upload file untuk setting
     *
     * @param  \CodeIgniter\HTTP\Files\UploadedFile $file
     * @param  string                               $oldFile      nama file lama (jika mau hapus)
     * @param  string                               $targetFolder folder tujuan relatif (default: 'uploads')
     * @return string|null                          nama file baru, atau null jika gagal
     */
    function uploadSettingFile($file, ?string $oldFile = null, string $targetFolder = 'uploads'): ?string
    {
        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Buat nama acak + timestamp
            $newName = time() . '_' . $file->getRandomName();

            // Pindahkan ke folder
            $file->move(ROOTPATH . 'public/' . $targetFolder, $newName);

            // Hapus file lama (jika ada)
            if (!empty($oldFile) && file_exists(ROOTPATH . 'public/' . $targetFolder . '/' . $oldFile)) {
                unlink(ROOTPATH . 'public/' . $targetFolder . '/' . $oldFile);
            }

            return $newName;
        }

        return null;
    }
}
