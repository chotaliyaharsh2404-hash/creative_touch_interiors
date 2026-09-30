<?php
require_once __DIR__ . '/../includes/config.php';

echo "=== FILE UPLOAD SECURITY AUDIT ===\n";

// 1. Check profile upload validation logic
$tempPhp = tempnam(sys_get_temp_dir(), 'test_evil_') . '.php';
file_put_contents($tempPhp, '<?php echo "evil shell"; ?>');

$fakeFile = [
    'name' => 'malicious.php',
    'tmp_name' => $tempPhp,
    'size' => filesize($tempPhp),
    'error' => UPLOAD_ERR_OK
];

$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$real_mime = mime_content_type($fakeFile['tmp_name']);
$extension = strtolower(pathinfo($fakeFile['name'], PATHINFO_EXTENSION));

$rejectedPhp = (!in_array($real_mime, $allowed_mimes) || !in_array($extension, $allowed_extensions));
echo "1. PHP shell file upload rejected: " . ($rejectedPhp ? "[PASS]" : "[FAIL]") . " (mime: $real_mime, ext: $extension)\n";

// 2. Disguised file (php script named .jpg)
$tempDisguised = tempnam(sys_get_temp_dir(), 'test_fake_') . '.jpg';
file_put_contents($tempDisguised, '<?php phpinfo(); ?>');
$real_mime_fake = mime_content_type($tempDisguised);
$rejectedDisguised = (!in_array($real_mime_fake, $allowed_mimes));
echo "2. PHP script disguised as .jpg rejected by real mime detection: " . ($rejectedDisguised ? "[PASS]" : "[FAIL]") . " (real mime: $real_mime_fake)\n";

// 3. Valid PNG image
$tempPng = tempnam(sys_get_temp_dir(), 'test_valid_') . '.png';
// 1x1 valid PNG binary
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($tempPng, $pngData);
$real_mime_png = mime_content_type($tempPng);
$ext_png = 'png';
$acceptedPng = (in_array($real_mime_png, $allowed_mimes) && in_array($ext_png, $allowed_extensions));
echo "3. Authentic PNG image accepted: " . ($acceptedPng ? "[PASS]" : "[FAIL]") . " (real mime: $real_mime_png)\n";

// Cleanup
@unlink($tempPhp);
@unlink($tempDisguised);
@unlink($tempPng);

echo "Upload Security Audit Completed.\n";
