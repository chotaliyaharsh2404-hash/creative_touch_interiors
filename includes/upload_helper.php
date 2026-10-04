<?php
/**
 * Creative Touch Interiors — Secure File Upload Helper
 * Version: v2.1.0
 * 
 * Provides robust, enterprise-grade validation and safe storage for image uploads.
 * - Allowed extensions: .jpg, .jpeg, .png, .webp
 * - MIME validation via finfo_file()
 * - True image verification via getimagesize()
 * - Strict 5 MB file size limit
 * - Cryptographically secure random filenames (bin2hex(random_bytes(16)))
 * - Strict blocking of executable/script extensions and multi-extension payloads
 * - Safe image replacement and safe deletion primitives
 * - Sanitized user-friendly error messages with zero internal information disclosure
 */

if (!defined('UPLOAD_MAX_BYTES')) {
    define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
}

// Allowed extensions
const SECURE_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

// Allowed MIME types mapped to their valid extensions
const SECURE_ALLOWED_MIMES = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp']
];

// Blocked dangerous extensions (checked against all dot-separated tokens)
const SECURE_DANGEROUS_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
    'cgi', 'pl', 'py', 'pyc', 'pyo', 'js', 'html', 'htm', 'xhtml', 'shtml', 'svg',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'vbs', 'scr', 'msi', 'com',
    'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd', 'ini', 'config', 'env'
];

/**
 * Validates and securely uploads an image file.
 *
 * @param array  $file       The $_FILES['key'] array
 * @param string $subfolder  Target subfolder inside uploads/ (e.g. 'blogs', 'team')
 * @param string $prefix     Filename prefix (e.g. 'blog_', 'team_')
 * @return array ['success' => bool, 'filepath' => string|null, 'filename' => string|null, 'error' => string|null]
 */
function secure_upload_image($file, $subfolder = 'blogs', $prefix = 'blog_') {
    // 1. Basic array structure check
    if (!is_array($file) || !isset($file['error'])) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Please select a valid image file to upload.'];
    }

    // 2. Upload error codes
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Please select a valid image file to upload.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'File size must be less than 5 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Upload failed. Please try again.'];
    }

    // 3. File size verification (Max 5 MB)
    if (!isset($file['size']) || $file['size'] > UPLOAD_MAX_BYTES) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'File size must be less than 5 MB.'];
    }
    if ($file['size'] <= 0) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'The uploaded file is not a valid image.'];
    }

    // 4. Temporary file path verification
    $tmpPath = $file['tmp_name'] ?? '';
    $isValidUpload = is_uploaded_file($tmpPath) || (defined('CLI_TEST_MODE') && CLI_TEST_MODE && file_exists($tmpPath));
    if (empty($tmpPath) || !$isValidUpload || !file_exists($tmpPath)) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Upload failed. Please try again.'];
    }

    // Double check real disk size
    $realSize = @filesize($tmpPath);
    if ($realSize === false || $realSize > UPLOAD_MAX_BYTES) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'File size must be less than 5 MB.'];
    }
    if ($realSize === 0) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'The uploaded file is not a valid image.'];
    }

    // 5. Original filename checks & dangerous extension rejection
    $origName = $file['name'] ?? '';
    if (strpos($origName, "\0") !== false) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
    }

    // Check every dot-separated part for dangerous extensions (e.g., shell.php.jpg)
    $nameParts = explode('.', strtolower($origName));
    if (count($nameParts) < 2) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
    }
    array_shift($nameParts); // Remove the leading base name
    foreach ($nameParts as $part) {
        $part = trim($part);
        if (in_array($part, SECURE_DANGEROUS_EXTENSIONS, true)) {
            return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
        }
    }

    // Check extension strictly against allowed list
    $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($origExt, SECURE_ALLOWED_EXTENSIONS, true)) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
    }

    // 6. Real MIME type detection using finfo_file()
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Upload failed. Please try again.'];
    }
    $realMime = @finfo_file($finfo, $tmpPath);
    @finfo_close($finfo);

    if (empty($realMime) || !array_key_exists($realMime, SECURE_ALLOWED_MIMES)) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
    }

    // 7. Verification that the file is actually an authentic image
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'The uploaded file is not a valid image.'];
    }

    // Validate that getimagesize() image type constant matches the detected MIME
    $expectedTypes = [
        'image/jpeg' => [IMAGETYPE_JPEG],
        'image/png'  => [IMAGETYPE_PNG],
        'image/webp' => [IMAGETYPE_WEBP]
    ];
    if (!in_array($imageInfo[2], $expectedTypes[$realMime], true)) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'The uploaded file is not a valid image.'];
    }

    // 8. Determine safe canonical extension
    if ($realMime === 'image/jpeg') {
        $safeExt = ($origExt === 'jpeg') ? 'jpeg' : 'jpg';
    } elseif ($realMime === 'image/png') {
        $safeExt = 'png';
    } elseif ($realMime === 'image/webp') {
        $safeExt = 'webp';
    } else {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Invalid image type.'];
    }

    // 9. Generate secure random filename: prefix + bin2hex(random_bytes(16)) + .extension
    try {
        $randomHex = bin2hex(random_bytes(16));
    } catch (\Exception $e) {
        $randomHex = bin2hex(openssl_random_pseudo_bytes(16));
    }
    $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    if (empty($cleanPrefix)) {
        $cleanPrefix = 'img_';
    }
    $safeFilename = $cleanPrefix . $randomHex . '.' . $safeExt;

    // 10. Prepare safe target directory
    $cleanSubfolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $subfolder);
    if (empty($cleanSubfolder)) {
        $cleanSubfolder = 'blogs';
    }

    $projectRoot = dirname(__DIR__);
    $targetDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $cleanSubfolder;
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'error' => 'Upload failed. Please try again.'];
        }
    }

    // Ensure uploads directory execution protection (.htaccess)
    ensure_upload_directory_protection();

    $fullDiskPath = $targetDir . DIRECTORY_SEPARATOR . $safeFilename;

    // 11. Move uploaded file
    $moved = false;
    if (is_uploaded_file($tmpPath)) {
        $moved = @move_uploaded_file($tmpPath, $fullDiskPath);
    } elseif (defined('CLI_TEST_MODE') && CLI_TEST_MODE) {
        $moved = @copy($tmpPath, $fullDiskPath);
    }

    if (!$moved || !file_exists($fullDiskPath)) {
        return ['success' => false, 'filepath' => null, 'filename' => null, 'error' => 'Upload failed. Please try again.'];
    }

    @chmod($fullDiskPath, 0644);

    // Return the safe database path (relative to webroot)
    $dbPath = 'uploads/' . $cleanSubfolder . '/' . $safeFilename;

    return [
        'success'   => true,
        'filepath'  => $dbPath,
        'filename'  => $safeFilename,
        'disk_path' => $fullDiskPath,
        'error'     => null
    ];
}

/**
 * Safely deletes an uploaded image file from disk.
 * Strictly prevents path traversal and guards default/seed assets against deletion.
 *
 * @param string|null $relPath Path stored in DB (e.g. 'uploads/blogs/blog_xxx.jpg')
 * @return bool True if deleted or did not exist, false if illegal path or protected asset
 */
function safe_delete_uploaded_image($relPath) {
    if (empty($relPath) || !is_string($relPath)) {
        return false;
    }

    $cleanRel = str_replace('\\', '/', trim($relPath));
    $cleanRel = ltrim($cleanRel, '/');

    // Reject path traversal attempts or control characters
    if (strpos($cleanRel, '..') !== false || strpos($cleanRel, ':') !== false || strpos($cleanRel, "\0") !== false) {
        return false;
    }

    // Target must reside in the uploads/ directory
    if (strpos($cleanRel, 'uploads/') !== 0) {
        return false;
    }

    // List of protected system/seed assets that must NEVER be deleted
    $protectedBasenames = [
        'harsh.jpeg', 'het.jpg', '1786870500_project_05.jpg', '1786869078_project_01.jpg',
        '1786884033_project_07.jpg', '1786885208_gallery_01.jpg', '1786886853_gallery_02.jpg',
        '1786887080_gallery_03.jpg', '1786887116_gallery_04.jpg', '1786887131_gallery_05.jpg',
        '1786887149_gallery_06.jpg', '1786887168_gallery_07.jpg', '1786887188_gallery_08.jpg',
        '1786887225_gallery_09.jpg', '1786887248_gallery_10.jpg', '1786887286_gallery_11.jpg',
        '1786887299_gallery_12.jpg', '1786887318_gallery_13.jpg', '1786887332_gallery_14.jpg',
        '1786887347_gallery_15.jpg', '1786887368_gallery_16.jpg', '1786887381_gallery_17.jpg',
        '1786887396_gallery_18.jpg', '1786887412_gallery_19.jpg', '1786887426_gallery_20.jpg',
        '1786887440_gallery_21.jpg', '1786887453_gallery_22.jpg', '1786887464_gallery_23.jpg',
        '1786887479_gallery_24.jpg', '1786887492_gallery_25.jpg'
    ];
    $baseName = strtolower(basename($cleanRel));
    if (in_array($baseName, $protectedBasenames, true)) {
        return false;
    }

    $projectRoot = dirname(__DIR__);
    $uploadsDir = realpath($projectRoot . DIRECTORY_SEPARATOR . 'uploads');
    $targetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRel);

    if (!file_exists($targetPath)) {
        return true; // Already gone
    }

    $realTarget = realpath($targetPath);
    if ($realTarget === false || $uploadsDir === false) {
        return false;
    }

    // Enforce that real path is strictly within the uploads directory
    if (strpos($realTarget, $uploadsDir) !== 0) {
        return false;
    }

    return @unlink($realTarget);
}

/**
 * Ensures .htaccess protection exists inside the uploads directory to prevent
 * server-side execution of any dangerous script.
 */
function ensure_upload_directory_protection() {
    $projectRoot = dirname(__DIR__);
    $uploadsDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $htaccessPath = $uploadsDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccessPath)) {
        $htaccessContent = "# Creative Touch Interiors - Upload Directory Security Guard\n"
            . "# Prevent script execution inside uploads\n"
            . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n"
            . "Options -ExecCGI -Indexes\n"
            . "AddHandler cgi-script .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py\n"
            . "<FilesMatch \"(?i:\\.(php|phtml|php3|php4|php5|php7|php8|phps|phar|cgi|pl|py|pyc|pyo|js|html|htm|exe|sh|bat|cmd|vbs|msi|asp|aspx|jsp))$\">\n"
            . "    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n";
        @file_put_contents($htaccessPath, $htaccessContent);
    }
}
