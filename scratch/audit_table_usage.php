<?php
require_once __DIR__ . '/../includes/config.php';

$tables = [
    'admin_users',
    'consultations',
    'contact_inquiries',
    'faqs',
    'gallery_images',
    'leads',
    'notifications',
    'projects',
    'quote_requests',
    'quote_rooms',
    'quote_services',
    'quote_status_history',
    'services',
    'team_members',
    'testimonials',
    'users',
    'website_content'
];

$rootPath = realpath(__DIR__ . '/..');

function getPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js', 'html', 'sql'])) {
            $filePath = $file->getRealPath();
            // skip tests and scratch
            if (strpos($filePath, DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, '.system_generated') !== false) {
                continue;
            }
            $files[] = $filePath;
        }
    }
    return $files;
}

$files = getPhpFiles($rootPath);
echo "Auditing " . count($files) . " code files against 17 database tables...\n\n";

$usage = [];
foreach ($tables as $table) {
    $usage[$table] = [
        'select' => [],
        'insert' => [],
        'update' => [],
        'delete' => [],
        'any' => []
    ];
}

foreach ($files as $filePath) {
    $relPath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    
    foreach ($tables as $table) {
        if (stripos($content, $table) !== false) {
            $usage[$table]['any'][] = $relPath;
            if (preg_match('/SELECT[^\n;]+FROM[^\n;]*\b' . preg_quote($table, '/') . '\b/i', $content)) {
                $usage[$table]['select'][] = $relPath;
            }
            if (preg_match('/INSERT\s+INTO\s+[`]?\b' . preg_quote($table, '/') . '\b/i', $content)) {
                $usage[$table]['insert'][] = $relPath;
            }
            if (preg_match('/UPDATE\s+[`]?\b' . preg_quote($table, '/') . '\b/i', $content)) {
                $usage[$table]['update'][] = $relPath;
            }
            if (preg_match('/DELETE\s+FROM\s+[`]?\b' . preg_quote($table, '/') . '\b/i', $content)) {
                $usage[$table]['delete'][] = $relPath;
            }
        }
    }
}

foreach ($usage as $table => $data) {
    echo "========================================================\n";
    echo "TABLE: {$table}\n";
    echo "--------------------------------------------------------\n";
    echo "Referenced in files (" . count($data['any']) . "): " . implode(', ', $data['any']) . "\n";
    echo "INSERT: " . implode(', ', array_unique($data['insert'])) . "\n";
    echo "SELECT: " . implode(', ', array_unique($data['select'])) . "\n";
    echo "UPDATE: " . implode(', ', array_unique($data['update'])) . "\n";
    echo "DELETE: " . implode(', ', array_unique($data['delete'])) . "\n";
    echo "\n";
}
