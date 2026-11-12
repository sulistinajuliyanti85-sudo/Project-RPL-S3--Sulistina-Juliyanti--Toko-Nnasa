<?php
/**
 * Secure File Upload Helper
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/functions.php';

/**
 * Handle secure file upload for images
 * 
 * @param array $file $_FILES['input_name']
 * @param string $folder Subfolder in uploads (products, banners, categories)
 * @param array $options Custom options (max_size, allowed_ext)
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function handle_image_upload(array $file, string $folder = 'products', array $options = []): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Parameter upload tidak valid.'];
    }

    // Check PHP error codes
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'Tidak ada file yang diunggah.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'Ukuran file melebihi batas maksimal server.'];
        default:
            return ['success' => false, 'error' => 'Terjadi kesalahan sistem saat mengunggah file.'];
    }

    $maxSize = $options['max_size'] ?? MAX_UPLOAD_SIZE;
    if ($file['size'] > $maxSize) {
        $sizeMB = round($maxSize / (1024 * 1024), 1);
        return ['success' => false, 'error' => "Ukuran file terlalu besar (maksimal {$sizeMB}MB)."];
    }

    // Validate Extension
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowedExt = $options['allowed_ext'] ?? ALLOWED_IMAGE_EXT;

    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'error' => 'Format file tidak diizinkan. Gunakan JPG, PNG, WEBP, atau SVG.'];
    }

    // Validate MIME Type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml']
    ];

    $isValidMime = false;
    if (isset($allowedMimes[$ext]) && in_array($mime, $allowedMimes[$ext], true)) {
        $isValidMime = true;
    }

    if (!$isValidMime) {
        return ['success' => false, 'error' => 'Tipe konten file tidak valid atau dicurigai berbahaya.'];
    }

    // Destination target directory
    $targetDir = UPLOAD_PATH . trim($folder, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Generate unique, collision-proof filename
    $newFilename = $folder . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Gagal memindahkan file yang diunggah ke direktori tujuan.'];
    }

    return [
        'success' => true,
        'filename' => $newFilename,
        'path' => $targetPath,
        'url' => BASE_URL . 'public/uploads/' . $folder . '/' . $newFilename
    ];
}
